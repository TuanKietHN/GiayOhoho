<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\UserCoupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        return response()->json(Coupon::orderByDesc('id')->get()->map(fn(Coupon $coupon) => $this->payload($coupon))->values());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|unique:coupons,code',
            'description' => 'nullable|string',
            'discount_type' => 'required_without:discountType|string|in:PERCENTAGE,FIXED_AMOUNT',
            'discountType' => 'required_without:discount_type|string|in:PERCENTAGE,FIXED_AMOUNT',
            'discount_value' => 'required_without:discountValue|numeric',
            'discountValue' => 'required_without:discount_value|numeric',
            'min_purchase' => 'nullable|numeric',
            'minPurchase' => 'nullable|numeric',
            'max_discount' => 'nullable|numeric',
            'maxDiscount' => 'nullable|numeric',
            'start_date' => 'required_without:startDate|date',
            'startDate' => 'required_without:start_date|date',
            'end_date' => 'required_without:endDate|date',
            'endDate' => 'required_without:end_date|date',
            'usage_limit' => 'nullable|integer',
            'usageLimit' => 'nullable|integer',
            'is_active' => 'boolean',
            'isActive' => 'nullable|boolean',
            'applicableScope' => 'nullable|string|in:ALL,BRAND,PRODUCT,VARIANT',
            'applicableBrand' => 'nullable|string|max:100',
            'applicableProductId' => 'nullable|integer|exists:products,id',
            'applicableVariantId' => 'nullable|integer|exists:product_variants,id',
        ]);
        $data = $this->normalize($data);
        $coupon = Coupon::create($data);
        return response()->json($this->payload($coupon), 201);
    }

    public function update(Request $request, int $id)
    {
        $coupon = Coupon::findOrFail($id);
        $data = $request->validate([
            'description' => 'nullable|string',
            'code' => 'nullable|string|unique:coupons,code,'.$coupon->id,
            'discount_type' => 'nullable|string|in:PERCENTAGE,FIXED_AMOUNT',
            'discountType' => 'nullable|string|in:PERCENTAGE,FIXED_AMOUNT',
            'discount_value' => 'nullable|numeric',
            'discountValue' => 'nullable|numeric',
            'min_purchase' => 'nullable|numeric',
            'minPurchase' => 'nullable|numeric',
            'max_discount' => 'nullable|numeric',
            'maxDiscount' => 'nullable|numeric',
            'start_date' => 'nullable|date',
            'startDate' => 'nullable|date',
            'end_date' => 'nullable|date',
            'endDate' => 'nullable|date',
            'usage_limit' => 'nullable|integer',
            'usageLimit' => 'nullable|integer',
            'is_active' => 'boolean',
            'isActive' => 'nullable|boolean',
            'applicableScope' => 'nullable|string|in:ALL,BRAND,PRODUCT,VARIANT',
            'applicableBrand' => 'nullable|string|max:100',
            'applicableProductId' => 'nullable|integer|exists:products,id',
            'applicableVariantId' => 'nullable|integer|exists:product_variants,id',
        ]);
        $data = $this->normalize($data);
        $coupon->update($data);
        return response()->json($this->payload($coupon->refresh()));
    }

    public function destroy(int $id)
    {
        Coupon::findOrFail($id)->delete();
        return response()->json(['message' => 'deleted']);
    }

    public function stats(int $id)
    {
        $coupon = Coupon::findOrFail($id);
        $usage = UserCoupon::with('user')->where('coupon_id', $id)->orderByDesc('used_at')->get();
        return response()->json(['coupon' => $coupon, 'usage' => $usage]);
    }

    private function normalize(array $data): array
    {
        foreach ([
            'discountType' => 'discount_type',
            'discountValue' => 'discount_value',
            'minPurchase' => 'min_purchase',
            'maxDiscount' => 'max_discount',
            'startDate' => 'start_date',
            'endDate' => 'end_date',
            'usageLimit' => 'usage_limit',
            'isActive' => 'is_active',
            'applicableScope' => 'applicable_scope',
            'applicableBrand' => 'applicable_brand',
            'applicableProductId' => 'applicable_product_id',
            'applicableVariantId' => 'applicable_variant_id',
        ] as $from => $to) {
            if (array_key_exists($from, $data)) {
                $data[$to] = $data[$from];
            }
            unset($data[$from]);
        }

        return $data;
    }

    private function payload(Coupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'description' => $coupon->description,
            'discountType' => $coupon->discount_type,
            'discount_type' => $coupon->discount_type,
            'discountValue' => (float) $coupon->discount_value,
            'discount_value' => (float) $coupon->discount_value,
            'minPurchase' => $coupon->min_purchase,
            'min_purchase' => $coupon->min_purchase,
            'maxDiscount' => $coupon->max_discount,
            'max_discount' => $coupon->max_discount,
            'startDate' => $coupon->start_date,
            'start_date' => $coupon->start_date,
            'endDate' => $coupon->end_date,
            'end_date' => $coupon->end_date,
            'usageLimit' => $coupon->usage_limit,
            'usage_limit' => $coupon->usage_limit,
            'timesUsed' => (int) $coupon->times_used,
            'times_used' => (int) $coupon->times_used,
            'isActive' => (bool) $coupon->is_active,
            'is_active' => (bool) $coupon->is_active,
            'applicableScope' => $coupon->applicable_scope ?? 'ALL',
            'applicableBrand' => $coupon->applicable_brand,
            'applicableProductId' => $coupon->applicable_product_id,
            'applicableVariantId' => $coupon->applicable_variant_id,
        ];
    }
}
