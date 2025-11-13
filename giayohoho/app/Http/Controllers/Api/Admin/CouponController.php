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
        return response()->json(Coupon::orderByDesc('id')->paginate(50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|unique:coupons,code',
            'description' => 'nullable|string',
            'discount_type' => 'required|string|in:PERCENTAGE,FIXED_AMOUNT',
            'discount_value' => 'required|numeric',
            'min_purchase' => 'nullable|numeric',
            'max_discount' => 'nullable|numeric',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'usage_limit' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);
        $coupon = Coupon::create($data);
        return response()->json($coupon, 201);
    }

    public function update(Request $request, int $id)
    {
        $coupon = Coupon::findOrFail($id);
        $data = $request->validate([
            'description' => 'nullable|string',
            'discount_type' => 'nullable|string|in:PERCENTAGE,FIXED_AMOUNT',
            'discount_value' => 'nullable|numeric',
            'min_purchase' => 'nullable|numeric',
            'max_discount' => 'nullable|numeric',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'usage_limit' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);
        $coupon->update($data);
        return response()->json($coupon);
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
}

