<?php

namespace App\Services\Payment;

use App\Models\OrderDetail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PayOsService
{
    public function configured(): bool
    {
        return (bool) (
            config('services.payos.client_id')
            && config('services.payos.api_key')
            && config('services.payos.checksum_key')
        );
    }

    public function createPaymentLink(OrderDetail $order, ?string $returnUrl = null, ?string $cancelUrl = null): array
    {
        $this->assertConfigured();

        $body = [
            'orderCode' => (int) $order->id,
            'amount' => (int) $order->total,
            'description' => Str::limit('OhGiay #'.$order->id, 25, ''),
            'returnUrl' => $returnUrl ?: (string) config('services.payos.return_url'),
            'cancelUrl' => $cancelUrl ?: (string) config('services.payos.cancel_url'),
        ];
        $body['signature'] = $this->signCreateRequest($body);

        $response = Http::timeout(20)
            ->acceptJson()
            ->withHeaders([
                'x-client-id' => (string) config('services.payos.client_id'),
                'x-api-key' => (string) config('services.payos.api_key'),
            ])
            ->post((string) config('services.payos.api_url'), $body);

        if (! $response->ok()) {
            throw new RuntimeException('PayOS request failed with HTTP '.$response->status().'.');
        }

        $payload = $response->json();
        if (($payload['code'] ?? null) !== '00') {
            throw new RuntimeException('PayOS rejected payment request: '.($payload['desc'] ?? $payload['message'] ?? 'unknown'));
        }

        $data = $payload['data'] ?? [];

        return [
            'request' => $body,
            'response' => $payload,
            'checkoutUrl' => $data['checkoutUrl'] ?? null,
            'paymentLinkId' => $data['paymentLinkId'] ?? null,
            'qrCode' => $data['qrCode'] ?? null,
            'orderCode' => $body['orderCode'],
        ];
    }

    public function verifyWebhook(array $payload): bool
    {
        $signature = (string) ($payload['signature'] ?? '');
        $data = $payload['data'] ?? null;

        if ($signature === '' || ! is_array($data)) {
            return false;
        }

        return hash_equals($signature, $this->hmac($this->buildDataString($data)));
    }

    public function normalizeWebhook(array $payload, string $rawPayload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $code = (string) ($data['code'] ?? $payload['code'] ?? '');
        $status = match (true) {
            $code === '00' => 'PAID',
            str_contains(strtolower((string) ($data['desc'] ?? $payload['desc'] ?? '')), 'cancel') => 'CANCELLED',
            $code !== '' => 'FAILED',
            default => 'RECEIVED',
        };

        $reference = (string) ($data['reference'] ?? $payload['reference'] ?? '');
        $paymentLinkId = (string) ($data['paymentLinkId'] ?? $payload['paymentLinkId'] ?? '');
        $eventKey = trim($paymentLinkId.':'.$reference.':'.$code, ':');

        return [
            'eventKey' => $eventKey !== '' ? $eventKey : hash('sha256', $rawPayload),
            'status' => $status,
            'orderCode' => isset($data['orderCode']) ? (int) $data['orderCode'] : null,
            'transactionId' => $paymentLinkId ?: ($reference ?: null),
            'data' => $data,
        ];
    }

    private function assertConfigured(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('PayOS is not configured. Set PAYOS_CLIENT_ID, PAYOS_API_KEY and PAYOS_CHECKSUM_KEY.');
        }
    }

    private function signCreateRequest(array $body): string
    {
        $data = 'amount='.$body['amount']
            .'&cancelUrl='.$body['cancelUrl']
            .'&description='.$body['description']
            .'&orderCode='.$body['orderCode']
            .'&returnUrl='.$body['returnUrl'];

        return $this->hmac($data);
    }

    private function hmac(string $data): string
    {
        return hash_hmac('sha256', $data, (string) config('services.payos.checksum_key'));
    }

    private function buildDataString(array $data): string
    {
        ksort($data);

        return collect($data)
            ->map(fn ($value, $key) => $key.'='.$this->stringify($value))
            ->implode('&');
    }

    private function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($this->sortRecursive($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function sortRecursive(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $nested) {
            if (is_array($nested)) {
                $value[$key] = $this->sortRecursive($nested);
            }
        }

        return $value;
    }
}
