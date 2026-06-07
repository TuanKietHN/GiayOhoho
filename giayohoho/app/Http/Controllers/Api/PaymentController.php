<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use App\Models\PaymentDetail;
use App\Models\PaymentEvent;
use App\Models\PaymentWebhookEvent;
use App\Services\Payment\PayOsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentController extends Controller
{
    public function init(Request $request, PayOsService $payOs)
    {
        $data = $request->validate([
            'orderId' => 'required_without:order_id|integer',
            'order_id' => 'required_without:orderId|integer',
            'provider' => 'nullable|string|max:50',
            'returnUrl' => 'nullable|string|max:500',
            'cancelUrl' => 'nullable|string|max:500',
        ]);

        $orderId = (int) ($data['orderId'] ?? $data['order_id']);
        $provider = strtoupper($data['provider'] ?? 'PAYOS');
        $order = OrderDetail::where('account_id', $request->user()->id)->findOrFail($orderId);

        $providerPayload = [
            'orderCode' => $order->id,
            'provider' => $provider,
            'checkoutUrl' => null,
        ];
        $transactionId = $provider.'-'.$order->id;

        if ($provider === 'PAYOS') {
            try {
                $providerPayload = $payOs->createPaymentLink($order, $data['returnUrl'] ?? null, $data['cancelUrl'] ?? null);
                $transactionId = $providerPayload['paymentLinkId'] ?? $transactionId;
            } catch (RuntimeException $exception) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                    'data' => null,
                    'timestamp' => now()->getTimestampMs(),
                ], 422);
            }
        }

        return DB::transaction(function () use ($order, $provider, $data, $providerPayload, $transactionId) {
            $payment = PaymentDetail::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'amount' => (int) $order->total,
                    'provider' => $provider,
                    'status' => 'PENDING',
                    'transaction_id' => $transactionId,
                    'return_url' => $data['returnUrl'] ?? null,
                    'cancel_url' => $data['cancelUrl'] ?? null,
                    'provider_data' => json_encode($providerPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'expires_at' => now()->addMinutes((int) config('services.payos.expiration_minutes', 30)),
                ]
            );
            $this->recordEvent($payment, null, 'PENDING', 'init');

            return $this->created($this->paymentPayload($payment->refresh()), 'Payment initialized');
        });
    }

    public function byOrder(Request $request, int $orderId)
    {
        $order = OrderDetail::where('account_id', $request->user()->id)->findOrFail($orderId);
        $payment = PaymentDetail::where('order_id', $order->id)->firstOrFail();

        return $this->ok($this->paymentPayload($payment));
    }

    public function payosReturnStatus(Request $request)
    {
        $data = $request->validate([
            'orderCode' => 'required|integer',
        ]);

        $order = OrderDetail::where('account_id', $request->user()->id)->findOrFail((int) $data['orderCode']);
        $payment = PaymentDetail::where('order_id', $order->id)->firstOrFail();

        return $this->ok($this->paymentPayload($payment));
    }

    public function cancel(Request $request, int $paymentId)
    {
        $payment = PaymentDetail::query()
            ->where(function ($query) use ($paymentId) {
                $query->where('id', $paymentId)
                    ->orWhere('order_id', $paymentId)
                    ->orWhere('transaction_id', 'PAYOS-'.$paymentId);
            })
            ->whereHas('order', fn($query) => $query->where('account_id', $request->user()->id))
            ->firstOrFail();

        if (in_array($payment->status, ['PAID', 'REFUNDED'], true)) {
            return response()->json(['message' => 'payment_not_cancelable'], 422);
        }

        return DB::transaction(function () use ($payment) {
            $from = $payment->status;
            $payment->forceFill(['status' => 'CANCELLED'])->save();
            $payment->order?->forceFill(['status' => 'CANCELLED'])->save();
            $this->recordEvent($payment, $from, 'CANCELLED', 'customer_cancel');

            return $this->ok($this->paymentPayload($payment->refresh()), 'Payment cancelled');
        });
    }

    public function payosWebhook(Request $request, PayOsService $payOs)
    {
        $rawPayload = $request->getContent();
        $payload = json_decode($rawPayload, true) ?: [];
        $verified = $payOs->verifyWebhook($payload);
        $normalized = $payOs->normalizeWebhook($payload, $rawPayload);

        if (! $verified) {
            PaymentWebhookEvent::updateOrCreate(
                ['provider' => 'PAYOS', 'event_key' => $normalized['eventKey']],
                [
                    'status' => 'FAILED',
                    'payload' => $rawPayload,
                    'error_message' => 'Invalid PayOS signature',
                    'processed_at' => now(),
                ]
            );

            return response()->json(['message' => 'invalid_signature'], 401);
        }

        $payment = null;
        if ($normalized['orderCode']) {
            $payment = PaymentDetail::query()
                ->where('order_id', $normalized['orderCode'])
                ->when($normalized['transactionId'], fn ($query) => $query->orWhere('transaction_id', $normalized['transactionId']))
                ->first();
        }

        return DB::transaction(function () use ($rawPayload, $normalized, $payment) {
            $event = PaymentWebhookEvent::firstOrNew([
                'provider' => 'PAYOS',
                'event_key' => $normalized['eventKey'],
            ]);
            $alreadyProcessed = $event->exists && $event->status === 'PROCESSED';

            $event->fill([
                'payment_id' => $payment?->id,
                'status' => $payment ? 'PROCESSED' : 'IGNORED',
                'payload' => $rawPayload,
                'error_message' => null,
                'processed_at' => now(),
            ])->save();

            if (! $alreadyProcessed && $payment && in_array($normalized['status'], ['PAID', 'CANCELLED', 'FAILED'], true)) {
                $from = $payment->status;
                $payment->forceFill([
                    'status' => $normalized['status'],
                    'transaction_id' => $normalized['transactionId'] ?: $payment->transaction_id,
                    'webhook_raw' => $rawPayload,
                    'webhook_idempotency_key' => $normalized['eventKey'],
                ])->save();

                if ($payment->order) {
                    $payment->order->forceFill(['status' => $normalized['status']])->save();
                }

                $this->recordEvent($payment, $from, $normalized['status'], 'payos_webhook', $rawPayload);
            }

            return response()->noContent();
        });
    }

    public function callback(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:order_details,id',
            'provider' => 'required|string',
            'status' => 'required|string|in:success,failed,paid,cancelled,PENDING,PAID,FAILED,CANCELLED',
            'amount' => 'nullable|numeric',
            'transaction_id' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($data) {
            $order = OrderDetail::findOrFail($data['order_id']);
            $status = strtoupper($data['status'] === 'success' ? 'PAID' : $data['status']);
            if ($status === 'PAID') {
                $order->status = 'PAID';
            } elseif (in_array($status, ['FAILED', 'CANCELLED'], true)) {
                $order->status = $status;
            }
            $order->save();

            $payment = PaymentDetail::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'amount' => (int) ($data['amount'] ?? $order->total),
                    'provider' => strtoupper($data['provider']),
                    'status' => $status,
                    'transaction_id' => $data['transaction_id'] ?? null,
                    'webhook_raw' => json_encode($data, JSON_UNESCAPED_UNICODE),
                ]
            );
            $this->recordEvent($payment, null, $status, 'callback', json_encode($data, JSON_UNESCAPED_UNICODE));

            return $this->ok(['order' => $order, 'payment' => $this->paymentPayload($payment)], 'updated');
        });
    }

    private function handleWebhook(string $provider, string $rawPayload)
    {
        $payload = json_decode($rawPayload, true) ?: [];
        $eventKey = (string) ($payload['id'] ?? $payload['eventId'] ?? hash('sha256', $rawPayload));
        $status = strtoupper((string) ($payload['status'] ?? $payload['data']['status'] ?? 'RECEIVED'));
        $orderCode = $payload['orderCode'] ?? $payload['data']['orderCode'] ?? $payload['order_id'] ?? null;
        $payment = null;

        if ($orderCode) {
            $payment = PaymentDetail::where('order_id', (int) $orderCode)
                ->orWhere('transaction_id', strtoupper($provider).'-'.(int) $orderCode)
                ->first();
        }

        return DB::transaction(function () use ($provider, $rawPayload, $eventKey, $status, $payment) {
            PaymentWebhookEvent::updateOrCreate(
                ['provider' => strtoupper($provider), 'event_key' => $eventKey],
                [
                    'payment_id' => $payment?->id,
                    'status' => $payment ? 'PROCESSED' : 'IGNORED',
                    'payload' => $rawPayload,
                    'processed_at' => now(),
                ]
            );

            if ($payment && in_array($status, ['PAID', 'SUCCESS', 'CANCELLED', 'FAILED'], true)) {
                $normalized = $status === 'SUCCESS' ? 'PAID' : $status;
                $from = $payment->status;
                $payment->forceFill([
                    'status' => $normalized,
                    'webhook_raw' => $rawPayload,
                    'webhook_idempotency_key' => $eventKey,
                ])->save();
                if ($payment->order && in_array($normalized, ['PAID', 'CANCELLED', 'FAILED'], true)) {
                    $payment->order->forceFill(['status' => $normalized])->save();
                }
                $this->recordEvent($payment, $from, $normalized, 'webhook', $rawPayload);
            }

            return response()->noContent();
        });
    }

    private function paymentPayload(PaymentDetail $payment): array
    {
        $providerData = json_decode($payment->provider_data ?: '{}', true) ?: [];

        return [
            'id' => $payment->id,
            'orderId' => $payment->order_id,
            'orderCode' => $payment->order_id,
            'amount' => (int) $payment->amount,
            'provider' => $payment->provider,
            'status' => $payment->status,
            'transactionId' => $payment->transaction_id,
            'checkoutUrl' => $providerData['checkoutUrl'] ?? null,
            'paymentUrl' => $providerData['paymentUrl'] ?? ($providerData['checkoutUrl'] ?? null),
            'paymentLinkId' => $providerData['paymentLinkId'] ?? null,
            'qrCode' => $providerData['qrCode'] ?? null,
            'returnUrl' => $payment->return_url,
            'cancelUrl' => $payment->cancel_url,
            'expiresAt' => $payment->expires_at,
        ];
    }

    private function recordEvent(PaymentDetail $payment, ?string $from, string $to, string $reason, ?string $raw = null): void
    {
        PaymentEvent::create([
            'payment_id' => $payment->id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'raw_data' => $raw,
            'created_at' => now(),
        ]);
    }
}
