<?php

namespace App\Services\Shipping;

use App\Models\OrderDetail;
use App\Models\ShippingOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use RuntimeException;

class GhnClient
{
    public function enabled(): bool
    {
        return (bool) config('services.ghn.enabled')
            && (bool) config('services.ghn.token')
            && (bool) config('services.ghn.base_url');
    }

    public function provinces(): array
    {
        return $this->post('/shiip/public-api/master-data/province');
    }

    public function districts(int $provinceId): array
    {
        return $this->post('/shiip/public-api/master-data/district', [
            'province_id' => $provinceId,
        ]);
    }

    public function wards(int $districtId): array
    {
        return $this->post('/shiip/public-api/master-data/ward', [
            'district_id' => $districtId,
        ]);
    }

    public function stores(): array
    {
        return $this->post('/shiip/public-api/v2/shop/all');
    }

    public function registerStore(array $payload): array
    {
        return $this->post('/shiip/public-api/v2/shop/register', $payload);
    }

    public function quote(array $data, int $itemCount = 1): array
    {
        $feeBody = $this->feePayload($data, $itemCount);
        $fee = $this->post('/shiip/public-api/v2/shipping-order/fee', $feeBody, true);

        $leadtime = null;
        if (! empty($feeBody['service_id'])) {
            $leadtime = $this->post('/shiip/public-api/v2/shipping-order/leadtime', [
                'from_district_id' => (int) config('services.ghn.from.district_id'),
                'from_ward_code' => (string) config('services.ghn.from.ward_code'),
                'to_district_id' => (int) $data['toDistrictId'],
                'to_ward_code' => (string) $data['toWardCode'],
                'service_id' => (int) $feeBody['service_id'],
            ], true);
        }

        $expected = Arr::get($leadtime, 'leadtime') ?: Arr::get($leadtime, 'data.leadtime');

        return [
            'provider' => 'GHN',
            'serviceId' => $feeBody['service_id'] ?? null,
            'serviceTypeId' => $feeBody['service_type_id'] ?? null,
            'serviceName' => 'GHN',
            'shippingFee' => (int) Arr::get($fee, 'total', 0),
            'insuranceFee' => (int) Arr::get($fee, 'insurance_fee', 0),
            'expectedDeliveryTime' => $expected ? now()->setTimestamp((int) $expected)->toIso8601String() : null,
            'quoteId' => 'GHN-'.now()->format('YmdHis').'-'.$data['toDistrictId'],
            'expiresAt' => now()->addMinutes((int) config('services.ghn.quote_ttl_minutes', 30))->toIso8601String(),
            'toProvinceId' => $data['toProvinceId'] ?? null,
            'toDistrictId' => (int) $data['toDistrictId'],
            'toWardCode' => (string) $data['toWardCode'],
            'lengthCm' => (int) config('services.ghn.defaults.length_cm'),
            'widthCm' => (int) config('services.ghn.defaults.width_cm'),
            'heightCm' => (int) config('services.ghn.defaults.height_cm'),
            'weightGrams' => (int) config('services.ghn.defaults.weight_grams'),
            'insuranceValue' => (int) config('services.ghn.defaults.insurance_value'),
            'feeBreakdown' => [
                'total' => (int) Arr::get($fee, 'total', 0),
                'serviceFee' => (int) Arr::get($fee, 'service_fee', 0),
                'insuranceFee' => (int) Arr::get($fee, 'insurance_fee', 0),
                'pickStationFee' => (int) Arr::get($fee, 'pick_station_fee', 0),
                'couponValue' => (int) Arr::get($fee, 'coupon_value', 0),
                'r2sFee' => (int) Arr::get($fee, 'r2s_fee', 0),
                'documentReturn' => (int) Arr::get($fee, 'document_return', 0),
                'doubleCheck' => (int) Arr::get($fee, 'double_check', 0),
                'pickRemoteAreasFee' => (int) Arr::get($fee, 'pick_remote_areas_fee', 0),
                'deliverRemoteAreasFee' => (int) Arr::get($fee, 'deliver_remote_areas_fee', 0),
            ],
        ];
    }

    public function previewOrder(OrderDetail $order, ShippingOrder $shipping): array
    {
        return $this->post('/shiip/public-api/v2/shipping-order/preview', $this->orderPayload($order, $shipping), true);
    }

    public function createOrder(OrderDetail $order, ShippingOrder $shipping): array
    {
        return $this->post('/shiip/public-api/v2/shipping-order/create', $this->orderPayload($order, $shipping), true);
    }

    public function detail(string $providerOrderCode): array
    {
        return $this->post('/shiip/public-api/v2/shipping-order/detail', [
            'order_code' => $providerOrderCode,
        ], true);
    }

    public function detailByClientCode(string $clientOrderCode): array
    {
        return $this->post('/shiip/public-api/v2/shipping-order/detail-by-client-code', [
            'client_order_code' => $clientOrderCode,
        ], true);
    }

    public function cancel(array $orderCodes): array
    {
        return $this->post('/shiip/public-api/v2/switch-status/cancel', [
            'order_codes' => array_values($orderCodes),
        ], true);
    }

    public function storing(array $orderCodes): array
    {
        return $this->post('/shiip/public-api/v2/switch-status/storing', [
            'order_codes' => array_values($orderCodes),
        ], true);
    }

    public function returnOrder(array $orderCodes): array
    {
        return $this->post('/shiip/public-api/v2/switch-status/return', [
            'order_codes' => array_values($orderCodes),
        ], true);
    }

    public function updateCod(string $providerOrderCode, int $codAmount): array
    {
        return $this->post('/shiip/public-api/v2/shipping-order/updateCOD', [
            'order_code' => $providerOrderCode,
            'cod_amount' => $codAmount,
        ], true);
    }

    public function printToken(array $orderCodes): array
    {
        return $this->post('/shiip/public-api/v2/a5/gen-token', [
            'order_codes' => array_values($orderCodes),
        ], true);
    }

    public function orderPayload(OrderDetail $order, ShippingOrder $shipping): array
    {
        $order->loadMissing('items.variant.product');

        $items = $order->items->map(function ($item) {
            $variant = $item->variant;
            $product = $variant?->product;

            return [
                'name' => $product?->name ?: 'OhGiay product',
                'code' => $variant?->sku ?: 'SKU-'.$item->product_variant_id,
                'quantity' => (int) $item->quantity,
                'price' => (int) $item->price,
                'length' => (int) config('services.ghn.defaults.length_cm'),
                'width' => (int) config('services.ghn.defaults.width_cm'),
                'height' => (int) config('services.ghn.defaults.height_cm'),
                'weight' => (int) config('services.ghn.defaults.weight_grams'),
            ];
        })->values()->all();

        return array_filter([
            'payment_type_id' => (int) config('services.ghn.defaults.payment_type_id'),
            'note' => 'OhGiay order '.$order->id,
            'required_note' => (string) config('services.ghn.defaults.required_note'),
            'from_name' => (string) config('services.ghn.from.name'),
            'from_phone' => (string) config('services.ghn.from.phone'),
            'from_address' => (string) config('services.ghn.from.address'),
            'from_ward_name' => config('services.ghn.from.ward_name'),
            'from_district_name' => config('services.ghn.from.district_name'),
            'from_province_name' => config('services.ghn.from.province_name'),
            'from_district_id' => (int) config('services.ghn.from.district_id'),
            'from_ward_code' => (string) config('services.ghn.from.ward_code'),
            'return_name' => config('services.ghn.return.name') ?: config('services.ghn.from.name'),
            'return_phone' => config('services.ghn.return.phone') ?: config('services.ghn.from.phone'),
            'return_address' => config('services.ghn.return.address') ?: config('services.ghn.from.address'),
            'return_district_id' => config('services.ghn.return.district_id') ?: config('services.ghn.from.district_id'),
            'return_ward_code' => config('services.ghn.return.ward_code') ?: config('services.ghn.from.ward_code'),
            'client_order_code' => $shipping->client_order_code ?: 'ORDER-'.$order->id,
            'to_name' => $order->recipient_name ?: 'OhGiay customer',
            'to_phone' => $order->recipient_phone ?: config('services.ghn.defaults.fallback_phone'),
            'to_address' => $order->order_address,
            'to_ward_code' => $order->ghn_to_ward_code,
            'to_district_id' => $order->ghn_to_district_id,
            'cod_amount' => strtoupper((string) $order->payment_method) === 'COD' ? (int) $order->total : 0,
            'cod_failed_amount' => (int) config('services.ghn.defaults.cod_failed_amount'),
            'insurance_value' => (int) ($shipping->insurance_value ?: config('services.ghn.defaults.insurance_value')),
            'service_id' => $order->shipping_service_id,
            'service_type_id' => $order->shipping_service_type_id ?: (int) config('services.ghn.defaults.service_type_id'),
            'length' => (int) config('services.ghn.defaults.length_cm'),
            'width' => (int) config('services.ghn.defaults.width_cm'),
            'height' => (int) config('services.ghn.defaults.height_cm'),
            'weight' => (int) config('services.ghn.defaults.weight_grams'),
            'items' => $items,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function feePayload(array $data, int $itemCount): array
    {
        $payload = [
            'from_district_id' => (int) config('services.ghn.from.district_id'),
            'from_ward_code' => (string) config('services.ghn.from.ward_code'),
            'to_district_id' => (int) $data['toDistrictId'],
            'to_ward_code' => (string) $data['toWardCode'],
            'service_type_id' => (int) ($data['serviceTypeId'] ?? config('services.ghn.defaults.service_type_id')),
            'height' => (int) config('services.ghn.defaults.height_cm'),
            'length' => (int) config('services.ghn.defaults.length_cm'),
            'weight' => max(1, $itemCount) * (int) config('services.ghn.defaults.weight_grams'),
            'width' => (int) config('services.ghn.defaults.width_cm'),
            'insurance_value' => (int) config('services.ghn.defaults.insurance_value'),
            'coupon' => $data['coupon'] ?? null,
        ];

        if (! empty($data['serviceId'])) {
            $payload['service_id'] = (int) $data['serviceId'];
            unset($payload['service_type_id']);
        }

        return array_filter($payload, fn ($value) => $value !== null && $value !== '');
    }

    private function post(string $path, array $body = [], bool $withShop = false): array
    {
        $this->assertConfigured();

        $response = Http::timeout(20)
            ->acceptJson()
            ->withHeaders($this->headers($withShop))
            ->post(rtrim((string) config('services.ghn.base_url'), '/').$path, $body);

        if (! $response->ok()) {
            throw new RuntimeException('GHN request failed with HTTP '.$response->status().'.');
        }

        $payload = $response->json();
        $code = $payload['code'] ?? null;
        if ($code !== null && (int) $code !== 200) {
            throw new RuntimeException('GHN rejected request: '.($payload['message'] ?? 'unknown'));
        }

        return is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
    }

    private function headers(bool $withShop): array
    {
        $headers = [
            'Token' => (string) config('services.ghn.token'),
        ];

        if ($withShop && config('services.ghn.shop_id')) {
            $headers['ShopId'] = (string) config('services.ghn.shop_id');
        }

        return $headers;
    }

    private function assertConfigured(): void
    {
        if (! $this->enabled()) {
            throw new RuntimeException('GHN is not configured. Set GHN_ENABLED=true, GHN_TOKEN and GHN_BASE_URL.');
        }
    }
}
