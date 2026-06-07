<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Shipping\GhnClient;
use Illuminate\Http\Request;
use RuntimeException;

class ShippingController extends Controller
{
    private const PROVINCES = [
        ['provinceId' => 201, 'provinceName' => 'Hà Nội', 'code' => 'HN'],
        ['provinceId' => 202, 'provinceName' => 'Hồ Chí Minh', 'code' => 'HCM'],
        ['provinceId' => 203, 'provinceName' => 'Đà Nẵng', 'code' => 'DN'],
        ['provinceId' => 204, 'provinceName' => 'Cần Thơ', 'code' => 'CT'],
        ['provinceId' => 205, 'provinceName' => 'Hải Phòng', 'code' => 'HP'],
    ];

    public function provinces(GhnClient $ghn)
    {
        if ($ghn->enabled()) {
            try {
                return $this->ok(collect($ghn->provinces())->map(fn ($province) => [
                    'provinceId' => $province['ProvinceID'] ?? $province['province_id'] ?? $province['provinceId'] ?? null,
                    'provinceName' => $province['ProvinceName'] ?? $province['province_name'] ?? $province['provinceName'] ?? null,
                    'code' => $province['Code'] ?? $province['code'] ?? null,
                ])->values());
            } catch (RuntimeException $exception) {
                return $this->providerError($exception);
            }
        }

        return $this->ok(self::PROVINCES);
    }

    public function districts(Request $request, GhnClient $ghn)
    {
        $provinceId = (int) $request->query('provinceId');

        if ($ghn->enabled()) {
            try {
                return $this->ok(collect($ghn->districts($provinceId))->map(fn ($district) => [
                    'districtId' => $district['DistrictID'] ?? $district['district_id'] ?? $district['districtId'] ?? null,
                    'provinceId' => $district['ProvinceID'] ?? $district['province_id'] ?? $provinceId,
                    'districtName' => $district['DistrictName'] ?? $district['district_name'] ?? $district['districtName'] ?? null,
                    'code' => $district['Code'] ?? $district['code'] ?? null,
                ])->values());
            } catch (RuntimeException $exception) {
                return $this->providerError($exception);
            }
        }

        $districts = [
            ['districtId' => $provinceId * 100 + 1, 'provinceId' => $provinceId, 'districtName' => 'Quận trung tâm', 'code' => 'QTT'],
            ['districtId' => $provinceId * 100 + 2, 'provinceId' => $provinceId, 'districtName' => 'Quận phía bắc', 'code' => 'QPB'],
            ['districtId' => $provinceId * 100 + 3, 'provinceId' => $provinceId, 'districtName' => 'Quận phía nam', 'code' => 'QPN'],
        ];

        return $this->ok($districts);
    }

    public function wards(Request $request, GhnClient $ghn)
    {
        $districtId = (int) $request->query('districtId');

        if ($ghn->enabled()) {
            try {
                return $this->ok(collect($ghn->wards($districtId))->map(fn ($ward) => [
                    'wardCode' => $ward['WardCode'] ?? $ward['ward_code'] ?? $ward['wardCode'] ?? null,
                    'districtId' => $ward['DistrictID'] ?? $ward['district_id'] ?? $districtId,
                    'wardName' => $ward['WardName'] ?? $ward['ward_name'] ?? $ward['wardName'] ?? null,
                ])->values());
            } catch (RuntimeException $exception) {
                return $this->providerError($exception);
            }
        }

        $wards = [
            ['wardCode' => (string) ($districtId * 10 + 1), 'districtId' => $districtId, 'wardName' => 'Phường 1'],
            ['wardCode' => (string) ($districtId * 10 + 2), 'districtId' => $districtId, 'wardName' => 'Phường 2'],
            ['wardCode' => (string) ($districtId * 10 + 3), 'districtId' => $districtId, 'wardName' => 'Phường 3'],
        ];

        return $this->ok($wards);
    }

    public function quote(Request $request, GhnClient $ghn)
    {
        $data = $request->validate([
            'toProvinceId' => 'nullable|integer',
            'toDistrictId' => 'required|integer',
            'toWardCode' => 'required|string|max:50',
            'serviceId' => 'nullable|integer',
            'serviceTypeId' => 'nullable|integer',
            'coupon' => 'nullable|string|max:100',
            'addressLine' => 'nullable|string|max:255',
            'selectedCartItemIds' => 'nullable|array',
        ]);

        $itemCount = count($data['selectedCartItemIds'] ?? []);

        if ($ghn->enabled()) {
            try {
                return $this->ok($ghn->quote($data, max(1, $itemCount)));
            } catch (RuntimeException $exception) {
                return $this->providerError($exception);
            }
        }

        return $this->ok($this->localQuote($data, $itemCount));
    }

    private function localQuote(array $data, int $itemCount): array
    {
        $shippingFee = 30000 + max(0, $itemCount - 1) * 5000;
        $serviceFee = $shippingFee;
        $expectedDeliveryTime = now()->addDays(3)->toIso8601String();

        return [
            'provider' => 'GHN',
            'serviceId' => 53320,
            'serviceTypeId' => 2,
            'serviceName' => 'GHN tiêu chuẩn',
            'shippingFee' => $shippingFee,
            'insuranceFee' => 0,
            'expectedDeliveryTime' => $expectedDeliveryTime,
            'quoteId' => 'LOCAL-GHN-'.now()->format('YmdHis').'-'.$data['toDistrictId'],
            'expiresAt' => now()->addMinutes(30)->toIso8601String(),
            'toProvinceId' => $data['toProvinceId'] ?? null,
            'toDistrictId' => $data['toDistrictId'],
            'toWardCode' => $data['toWardCode'],
            'lengthCm' => 32,
            'widthCm' => 22,
            'heightCm' => 12,
            'weightGrams' => 1000,
            'insuranceValue' => 0,
            'feeBreakdown' => [
                'total' => $shippingFee,
                'serviceFee' => $serviceFee,
                'insuranceFee' => 0,
                'pickStationFee' => 0,
                'couponValue' => 0,
                'r2sFee' => 0,
                'documentReturn' => 0,
                'doubleCheck' => 0,
                'pickRemoteAreasFee' => 0,
                'deliverRemoteAreasFee' => 0,
            ],
        ];
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
