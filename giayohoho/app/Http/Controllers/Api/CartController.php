<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CartController extends Controller
{
    protected function getOrCreateCart(int $userId): Cart
    {
        $cart = Cart::firstOrCreate(['user_id' => $userId]);
        return $cart->load(['items.variant.product', 'coupon']);
    }

    protected function recalc(Cart $cart): Cart
    {
        $sub = 0;
        foreach ($cart->items as $item) {
            $sub += (int) $item->price * (int) $item->quantity;
        }
        $discount = 0;
        if ($cart->coupon) {
            $now = Carbon::now();
            $valid = $cart->coupon->is_active
                && $now->between(Carbon::parse($cart->coupon->start_date), Carbon::parse($cart->coupon->end_date))
                && (! $cart->coupon->min_purchase || $sub >= (float) $cart->coupon->min_purchase);
            if ($valid) {
                if ($cart->coupon->discount_type === 'PERCENTAGE') {
                    $discount = $sub * ((float) $cart->coupon->discount_value / 100);
                    if ($cart->coupon->max_discount) {
                        $discount = min($discount, (float) $cart->coupon->max_discount);
                    }
                } else {
                    $discount = (float) $cart->coupon->discount_value;
                }
            } else {
                $cart->coupon_id = null;
            }
        }
        $cart->sub_total = $sub;
        $cart->discount_amount = $discount;
        $cart->total = max(0, $sub - $discount);
        $cart->save();
        return $cart->refresh()->load(['items.variant.product', 'coupon']);
    }

    public function get(Request $request)
    {
        $cart = $this->getOrCreateCart($request->user()->id);
        $cart = $this->recalc($cart);
        return response()->json($cart);
    }

    public function addItem(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);
        $cart = $this->getOrCreateCart($request->user()->id);
        $variant = ProductVariant::with('product')->findOrFail($data['product_variant_id']);
        $price = (int) $variant->product->base_price + (int) $variant->extra_price;
        $item = CartItem::firstOrCreate([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
        ], [
            'quantity' => 0,
            'price' => $price,
        ]);
        $item->quantity += (int) $data['quantity'];
        $item->price = $price;
        $item->save();
        $cart = $this->recalc($this->getOrCreateCart($request->user()->id));
        return response()->json($cart);
    }

    public function updateItem(Request $request, int $id)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);
        $cart = $this->getOrCreateCart($request->user()->id);
        $item = CartItem::where('cart_id', $cart->id)->findOrFail($id);
        $item->quantity = (int) $data['quantity'];
        $item->save();
        $cart = $this->recalc($cart);
        return response()->json($cart);
    }

    public function removeItem(Request $request, int $id)
    {
        $cart = $this->getOrCreateCart($request->user()->id);
        $item = CartItem::where('cart_id', $cart->id)->findOrFail($id);
        $item->delete();
        $cart = $this->recalc($cart);
        return response()->json($cart);
    }

    public function applyCoupon(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string',
        ]);
        $cart = $this->getOrCreateCart($request->user()->id);
        $coupon = Coupon::where('code', $data['code'])->first();
        if (! $coupon) {
            return response()->json(['message' => 'invalid_code'], 422);
        }
        $cart->coupon_id = $coupon->id;
        $cart->save();
        $cart = $this->recalc($cart);
        return response()->json($cart);
    }
}

