<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use App\Models\ShippingOrder;
use App\Services\Shipping\GhnClient;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use RuntimeException;

class ShippingController extends Controller
{
    public function stores(Request $request, GhnClient $ghn)
    {
        if ($ghn->enabled()) {
            try {
                return $this->ok($ghn->stores());
            } catch (RuntimeException $exception) {
                return $this->providerError($exception);
            }
        }

        return $this->ok([
            [
                'shopId' => (int) config('services.ghn.shop_id', 0),
                'name' => config('app.name', 'OhGiay'),
                'phone' => config('services.ghn.from.phone', ''),
                'address' => config('services.ghn.from.address', ''),
            ],
        ]);
    }

    public function createStore(Request $request, GhnClient $ghn)
    {
        if ($ghn->enabled()) {
            try {
                return $this->ok($ghn->registerStore($request->all()), 'GHN store created');
            } catch (RuntimeException $exception) {
                return $this->providerError($exception);
            }
        }

        return $this->ok([
            'shopId' => now()->timestamp,
            'name' => $request->input('name', config('app.name', 'OhGiay')),
            'phone' => $request->input('phone'),
            'address' => $request->input('address'),
        ], 'GHN store accepted locally');
    }

    public function show(int $orderId)
    {
        $shipping = ShippingOrder::where('order_id', $orderId)->firstOrFail();
        return $this->ok($this->payload($shipping));
    }

    public function preview(int $orderId, GhnClient $ghn)
    {
        $order = OrderDetail::with('items.variant.product')->findOrFail($orderId);
        $shipping = $this->ensureShipping($order, 'PREVIEW');

        if ($ghn->enabled()) {
            try {
                $preview = $ghn->previewOrder($order, $shipping);
                $shipping->forceFill([
                    'total_fee' => (int) Arr::get($preview, 'fee.total', Arr::get($preview, 'total_fee', $shipping->total_fee)),
                    'main_service_fee' => (int) Arr::get($preview, 'fee.service_fee', Arr::get($preview, 'service_fee', $shipping->main_service_fee)),
                    'raw_latest_payload' => json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ])->save();

                return $this->ok([
                    'accepted' => true,
                    'message' => 'GHN shipment preview generated',
                    'providerPayload' => $preview,
                    'shippingOrder' => $this->payload($shipping->refresh()),
                ]);
            } catch (RuntimeException $exception) {
                return $this->providerError($exception);
            }
        }

        return $this->ok([
            'accepted' => true,
            'message' => 'GHN shipment preview generated locally',
            'shippingOrder' => $this->payload($shipping),
        ]);
    }

    public function create(int $orderId, GhnClient $ghn)
    {
        $order = OrderDetail::with('items.variant.product')->findOrFail($orderId);
        $shipping = $this->ensureShipping($order, 'READY_TO_PICK');

        if ($ghn->enabled()) {
            try {
                $created = $ghn->createOrder($order, $shipping);
                $shipping->forceFill([
                    'provider_order_code' => Arr::get($created, 'order_code', Arr::get($created, 'OrderCode', $shipping->provider_order_code)),
                    'status_raw' => Arr::get($created, 'status', 'READY_TO_PICK'),
                    'status_mapped' => 'SHIPPING',
                    'total_fee' => (int) Arr::get($created, 'total_fee', $shipping->total_fee),
                    'expected_delivery_time' => Arr::get($created, 'expected_delivery_time', $shipping->expected_delivery_time),
                    'raw_create_response' => json_encode($created, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'raw_latest_payload' => json_encode($created, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ])->save();
                $order->forceFill(['status' => 'SHIPPING'])->save();

                return $this->ok($this->payload($shipping->refresh()), 'GHN shipment created');
            } catch (RuntimeException $exception) {
                return $this->providerError($exception);
            }
        }

        $shipping->forceFill([
            'provider_order_code' => $shipping->provider_order_code ?: 'LOCAL-GHN-'.$order->id,
            'status_raw' => 'READY_TO_PICK',
            'status_mapped' => 'SHIPPING',
            'raw_create_response' => json_encode(['local' => true], JSON_UNESCAPED_UNICODE),
        ])->save();
        $order->forceFill(['status' => 'SHIPPING'])->save();

        return $this->ok($this->payload($shipping->refresh()), 'GHN shipment created');
    }

    public function sync(int $orderId, GhnClient $ghn)
    {
        $shipping = ShippingOrder::where('order_id', $orderId)->firstOrFail();

        if ($ghn->enabled()) {
            try {
                $detail = $shipping->provider_order_code
                    ? $ghn->detail($shipping->provider_order_code)
                    : $ghn->detailByClientCode($shipping->client_order_code);
                $status = Arr::get($detail, 'status', Arr::get($detail, 'Status'));
                $shipping->forceFill([
                    'status_raw' => $status ?: $shipping->status_raw,
                    'status_mapped' => $this->mapStatus($status ?: $shipping->status_raw),
                    'reason_code' => Arr::get($detail, 'reason_code', $shipping->reason_code),
                    'reason_message' => Arr::get($detail, 'reason', $shipping->reason_message),
                    'raw_latest_payload' => json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ])->save();

                return $this->ok($this->payload($shipping->refresh()), 'GHN shipment synced');
            } catch (RuntimeException $exception) {
                return $this->providerError($exception);
            }
        }

        $shipping->forceFill([
            'raw_latest_payload' => json_encode(['syncedAt' => now()->toIso8601String()], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ])->save();

        return $this->ok($this->payload($shipping->refresh()), 'GHN shipment synced');
    }

    public function cancel(int $orderId, GhnClient $ghn)
    {
        $shipping = ShippingOrder::where('order_id', $orderId)->firstOrFail();

        if ($ghn->enabled()) {
            try {
                $result = $ghn->cancel($this->orderCodes($shipping));
                $shipping->forceFill([
                    'status_raw' => 'CANCELLED',
                    'status_mapped' => 'CANCELLED',
                    'raw_latest_payload' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ])->save();

                return $this->ok([
                    'accepted' => true,
                    'message' => 'GHN shipment cancelled',
                    'providerPayload' => $result,
                    'shippingOrder' => $this->payload($shipping->refresh()),
                ]);
            } catch (RuntimeException $exception) {
                return $this->providerError($exception);
            }
        }

        $shipping->forceFill([
            'status_raw' => 'CANCELLED',
            'status_mapped' => 'CANCELLED',
            'updated_at' => now(),
        ])->save();

        return $this->ok([
            'accepted' => true,
            'message' => 'GHN shipment cancelled locally',
            'shippingOrder' => $this->payload($shipping->refresh()),
        ]);
    }

    public function deliveryAgain(int $orderId, GhnClient $ghn)
    {
        return $this->action($orderId, 'DELIVERY_AGAIN', $ghn);
    }

    public function returnOrder(int $orderId, GhnClient $ghn)
    {
        return $this->action($orderId, 'RETURN', $ghn);
    }

    public function updateCod(Request $request, int $orderId, GhnClient $ghn)
    {
        $shipping = ShippingOrder::where('order_id', $orderId)->firstOrFail();
        $codAmount = (int) $request->input('codAmount', $request->input('cod_amount', 0));

        if ($ghn->enabled()) {
            try {
                if (! $shipping->provider_order_code) {
                    throw new RuntimeException('GHN provider order code is required for COD update.');
                }
                $result = $ghn->updateCod($shipping->provider_order_code, $codAmount);
                $shipping->forceFill([
                    'cod_amount' => $codAmount,
                    'raw_latest_payload' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ])->save();

                return $this->ok([
                    'accepted' => true,
                    'message' => 'COD amount updated',
                    'providerPayload' => $result,
                    'shippingOrder' => $this->payload($shipping->refresh()),
                ]);
            } catch (RuntimeException $exception) {
                return $this->providerError($exception);
            }
        }

        $shipping->forceFill(['cod_amount' => $codAmount])->save();

        return $this->ok([
            'accepted' => true,
            'message' => 'COD amount updated locally',
            'shippingOrder' => $this->payload($shipping->refresh()),
        ]);
    }

    public function printToken(int $orderId, GhnClient $ghn)
    {
        $shipping = ShippingOrder::where('order_id', $orderId)->firstOrFail();

        if ($ghn->enabled()) {
            try {
                $result = $ghn->printToken($this->orderCodes($shipping));
                $token = Arr::get($result, 'token', Arr::get($result, 'Token'));

                return $this->ok([
                    'token' => $token,
                    'providerPayload' => $result,
                    'printUrl' => config('services.ghn.print_base_url').'?token='.$token,
                ]);
            } catch (RuntimeException $exception) {
                return $this->providerError($exception);
            }
        }

        $token = base64_encode('print:'.$shipping->provider_order_code.':'.now()->timestamp);

        return $this->ok([
            'token' => $token,
            'printUrl' => url('/api/admin/orders/'.$orderId.'/shipping/ghn/print-token?token='.$token),
        ]);
    }

    private function action(int $orderId, string $status, GhnClient $ghn)
    {
        $shipping = ShippingOrder::where('order_id', $orderId)->firstOrFail();

        if ($ghn->enabled()) {
            try {
                $result = $status === 'RETURN'
                    ? $ghn->returnOrder($this->orderCodes($shipping))
                    : $ghn->storing($this->orderCodes($shipping));
                $shipping->forceFill([
                    'status_raw' => $status,
                    'status_mapped' => $status,
                    'raw_latest_payload' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ])->save();

                return $this->ok([
                    'accepted' => true,
                    'message' => 'GHN action accepted',
                    'providerPayload' => $result,
                    'shippingOrder' => $this->payload($shipping->refresh()),
                ]);
            } catch (RuntimeException $exception) {
                return $this->providerError($exception);
            }
        }

        $shipping->forceFill([
            'status_raw' => $status,
            'status_mapped' => $status,
            'updated_at' => now(),
        ])->save();

        return $this->ok([
            'accepted' => true,
            'message' => 'GHN action accepted locally',
            'shippingOrder' => $this->payload($shipping->refresh()),
        ]);
    }

    private function ensureShipping(OrderDetail $order, string $status): ShippingOrder
    {
        return ShippingOrder::updateOrCreate(
            ['order_id' => $order->id],
            [
                'provider' => 'GHN',
                'client_order_code' => 'ORDER-'.$order->id,
                'shop_id' => config('services.ghn.shop_id') ?: null,
                'from_name' => config('services.ghn.from.name'),
                'from_phone' => config('services.ghn.from.phone'),
                'from_address' => config('services.ghn.from.address'),
                'from_district_id' => config('services.ghn.from.district_id') ?: null,
                'from_ward_code' => config('services.ghn.from.ward_code'),
                'to_name' => $order->recipient_name,
                'to_phone' => $order->recipient_phone,
                'to_address' => $order->order_address,
                'to_district_id' => $order->ghn_to_district_id,
                'to_ward_code' => $order->ghn_to_ward_code,
                'return_name' => config('services.ghn.return.name') ?: config('services.ghn.from.name'),
                'return_phone' => config('services.ghn.return.phone') ?: config('services.ghn.from.phone'),
                'return_address' => config('services.ghn.return.address') ?: config('services.ghn.from.address'),
                'return_district_id' => config('services.ghn.return.district_id') ?: config('services.ghn.from.district_id'),
                'return_ward_code' => config('services.ghn.return.ward_code') ?: config('services.ghn.from.ward_code'),
                'service_id' => $order->shipping_service_id,
                'service_type_id' => $order->shipping_service_type_id,
                'payment_type_id' => config('services.ghn.defaults.payment_type_id'),
                'required_note' => config('services.ghn.defaults.required_note'),
                'cod_amount' => strtoupper((string) $order->payment_method) === 'COD' ? (int) $order->total : 0,
                'cod_failed_amount' => config('services.ghn.defaults.cod_failed_amount'),
                'insurance_value' => config('services.ghn.defaults.insurance_value'),
                'total_fee' => (int) $order->shipping_fee,
                'length_cm' => config('services.ghn.defaults.length_cm'),
                'width_cm' => config('services.ghn.defaults.width_cm'),
                'height_cm' => config('services.ghn.defaults.height_cm'),
                'weight_grams' => config('services.ghn.defaults.weight_grams'),
                'status_raw' => $status,
                'status_mapped' => $status === 'PREVIEW' ? 'PENDING' : 'SHIPPING',
                'expected_delivery_time' => $order->expected_delivery_time ?? now()->addDays(3),
            ]
        );
    }

    private function payload(ShippingOrder $shipping): array
    {
        return [
            'id' => $shipping->id,
            'orderId' => $shipping->order_id,
            'provider' => $shipping->provider,
            'providerOrderCode' => $shipping->provider_order_code,
            'clientOrderCode' => $shipping->client_order_code,
            'statusRaw' => $shipping->status_raw,
            'statusMapped' => $shipping->status_mapped,
            'reasonCode' => $shipping->reason_code,
            'reasonMessage' => $shipping->reason_message,
            'totalFee' => (int) $shipping->total_fee,
            'codAmount' => (int) $shipping->cod_amount,
            'insuranceValue' => (int) $shipping->insurance_value,
            'serviceId' => $shipping->service_id,
            'serviceTypeId' => $shipping->service_type_id,
            'expectedDeliveryTime' => $shipping->expected_delivery_time?->toIso8601String(),
            'createdAt' => $shipping->created_at?->toIso8601String(),
            'updatedAt' => $shipping->updated_at?->toIso8601String(),
        ];
    }

    private function orderCodes(ShippingOrder $shipping): array
    {
        if (! $shipping->provider_order_code) {
            throw new RuntimeException('GHN provider order code is required for this action.');
        }

        return [$shipping->provider_order_code];
    }

    private function mapStatus(?string $status): ?string
    {
        return match (strtolower((string) $status)) {
            'ready_to_pick', 'picking', 'picked', 'storing', 'transporting' => 'SHIPPING',
            'delivered' => 'DONE',
            'cancel', 'cancelled' => 'CANCELLED',
            'return', 'returned' => 'RETURNING',
            default => $status ? strtoupper($status) : null,
        };
    }

    private function providerError(RuntimeException $exception)
    {
        return response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
            'data' => null,
            'timestamp' => now()->getTimestampMs(),
        ], 502);
    }
}
