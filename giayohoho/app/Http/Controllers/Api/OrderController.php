<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\OrderDetail;
use App\Models\OrderItem;
use App\Models\Coupon;
use App\Services\Discount\DiscountCalculator;
use App\Models\UserCoupon;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function checkout(Request $request)
    {
        $data = $request->validate([
            'address_id' => 'nullable|integer|exists:addresses,id',
            'order_address' => 'nullable|string|max:255',
            'payment_method' => 'required|string',
            'coupon_code' => 'nullable|string',
        ]);

        $userId = $request->user()->id;
        $cart = Cart::with(['items.variant.product', 'coupon'])->where('user_id', $userId)->first();
        if (! $cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'empty_cart'], 422);
        }

        $coupon = null;
        if (! empty($data['coupon_code'])) {
            $coupon = Coupon::where('code', $data['coupon_code'])->first();
            if ($coupon) {
                $cart->coupon_id = $coupon->id;
            }
        } else {
            $coupon = $cart->coupon; // dùng coupon đang áp dụng trong giỏ
        }

        $sub = 0;
        foreach ($cart->items as $item) {
            $sub += (int) $item->price * (int) $item->quantity;
        }

        $discount = 0;
        if ($coupon) {
            $now = Carbon::now();
            $valid = $coupon->is_active
                && $now->between(Carbon::parse($coupon->start_date), Carbon::parse($coupon->end_date))
                && (! $coupon->min_purchase || $sub >= (float) $coupon->min_purchase);
            if ($valid) {
                $calc = new DiscountCalculator();
                $strategy = $calc->forCoupon($coupon);
                $discount = $strategy->calculate((float) $sub);
            }
        }
        $total = max(0, $sub - $discount);

        $orderAddress = $data['order_address'] ?? null;
        if (isset($data['address_id']) && $data['address_id']) {
            $orderAddress = $request->user()->addresses()->find($data['address_id'])?->address_line;
        }
        if (! $orderAddress) {
            return response()->json(['message' => 'order_address_required'], 422);
        }

        return DB::transaction(function () use ($userId, $sub, $discount, $total, $coupon, $orderAddress, $cart, $request) {
            $order = OrderDetail::create([
                'user_id' => $userId,
                'total' => $total,
                'sub_total' => $sub,
                'discount_amount' => $discount,
                'coupon_id' => $coupon?->id,
                'order_address' => $orderAddress,
                'status' => 'pending',
            ]);

            foreach ($cart->items as $item) {
                $variant = ProductVariant::lockForUpdate()->findOrFail($item->product_variant_id);
                if ((int) $variant->stock < (int) $item->quantity) {
                    abort(response()->json(['message' => 'out_of_stock', 'variant_id' => $variant->id], 422));
                }
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ]);
                $variant->decrement('stock', (int) $item->quantity);
            }

            if ($coupon) {
                UserCoupon::create([
                    'user_id' => $userId,
                    'coupon_id' => $coupon->id,
                    'order_id' => $order->id,
                    'used_at' => Carbon::now(),
                ]);
                $coupon->increment('times_used');
            }

            Cart::where('id', $cart->id)->delete();

            return response()->json($order->load(['items.variant.product']), 201);
        });
    }

    public function listOrders(Request $request)
    {
        $orders = OrderDetail::with(['items.variant.product'])->where('user_id', $request->user()->id)->orderByDesc('id')->get();
        return response()->json($orders);
    }

    public function show(Request $request, int $id)
    {
        $order = OrderDetail::with(['items.variant.product'])->where('user_id', $request->user()->id)->findOrFail($id);
        return response()->json($order);
    }
}
