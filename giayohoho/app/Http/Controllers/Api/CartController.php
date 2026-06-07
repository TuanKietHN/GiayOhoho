<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Services\Discount\DiscountCalculator;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CartController extends Controller
{
    protected function getOrCreateCart(int $accountId): Cart
    {
        $cart = Cart::firstOrCreate(['account_id' => $accountId]);
        return $cart->load(['items.variant.images', 'items.variant.product.images', 'coupon']);
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
                $calc = new DiscountCalculator();
                $strategy = $calc->forCoupon($cart->coupon);
                $discount = $strategy->calculate((float) $sub);
            } else {
                $cart->coupon_id = null;
            }
        }
        $cart->sub_total = $sub;
        $cart->discount_amount = $discount;
        $cart->total = max(0, $sub - $discount);
        $cart->save();
        return $cart->refresh()->load(['items.variant.images', 'items.variant.product.images', 'coupon']);
    }

    public function get(Request $request)
    {
        $cart = $this->getOrCreateCart($request->user()->id);
        $cart = $this->recalc($cart);
        return response()->json($this->cartPayload($cart));
    }

    public function addItem(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => 'nullable|integer|exists:product_variants,id',
            'variantId' => 'nullable|integer|exists:product_variants,id',
            'sku' => 'nullable|string|exists:product_variants,sku',
            'quantity' => 'required|integer|min:1',
        ]);
        if (! ($data['product_variant_id'] ?? $data['variantId'] ?? $data['sku'] ?? null)) {
            return response()->json(['message' => 'variant_required'], 422);
        }
        $cart = $this->getOrCreateCart($request->user()->id);
        $variant = ProductVariant::with('product')->when(
            isset($data['sku']),
            fn($q) => $q->where('sku', $data['sku']),
            fn($q) => $q->where('id', $data['product_variant_id'] ?? $data['variantId'])
        )->firstOrFail();
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
        return response()->json($this->cartPayload($cart));
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
        return response()->json($this->cartPayload($cart));
    }

    public function removeItem(Request $request, int $id)
    {
        $cart = $this->getOrCreateCart($request->user()->id);
        $item = CartItem::where('cart_id', $cart->id)->findOrFail($id);
        $item->delete();
        $cart = $this->recalc($cart);
        return response()->json($this->cartPayload($cart));
    }

    public function updateItemVariant(Request $request, int $id)
    {
        $data = $request->validate([
            'product_variant_id' => 'nullable|integer|exists:product_variants,id',
            'variantId' => 'nullable|integer|exists:product_variants,id',
            'sku' => 'nullable|string|exists:product_variants,sku',
        ]);
        if (! ($data['product_variant_id'] ?? $data['variantId'] ?? $data['sku'] ?? null)) {
            return response()->json(['message' => 'variant_required'], 422);
        }
        $cart = $this->getOrCreateCart($request->user()->id);
        $item = CartItem::where('cart_id', $cart->id)->findOrFail($id);
        $variant = ProductVariant::with('product')->when(
            isset($data['sku']),
            fn($q) => $q->where('sku', $data['sku']),
            fn($q) => $q->where('id', $data['product_variant_id'] ?? $data['variantId'])
        )->firstOrFail();
        $item->product_variant_id = $variant->id;
        $item->price = (int) $variant->product->base_price + (int) $variant->extra_price;
        $item->save();

        return response()->json($this->cartPayload($this->recalc($cart)));
    }

    public function clear(Request $request)
    {
        $cart = $this->getOrCreateCart($request->user()->id);
        CartItem::where('cart_id', $cart->id)->delete();
        $cart->coupon_id = null;
        $cart->save();

        return response()->json($this->cartPayload($this->recalc($cart)));
    }

    public function applyCoupon(Request $request)
    {
        $data = $request->validate([
            'code' => 'required_without:couponCode|string',
            'couponCode' => 'required_without:code|string',
        ]);
        $cart = $this->getOrCreateCart($request->user()->id);
        $coupon = Coupon::where('code', $data['code'] ?? $data['couponCode'])->first();
        if (! $coupon) {
            return response()->json(['message' => 'invalid_code'], 422);
        }
        $cart->coupon_id = $coupon->id;
        $cart->save();
        $cart = $this->recalc($cart);
        return response()->json($this->cartPayload($cart));
    }

    public function removeCoupon(Request $request)
    {
        $cart = $this->getOrCreateCart($request->user()->id);
        $cart->coupon_id = null;
        $cart->save();

        return response()->json($this->cartPayload($this->recalc($cart)));
    }

    private function cartPayload(Cart $cart): array
    {
        return [
            'id' => $cart->id,
            'accountId' => $cart->account_id,
            'account_id' => $cart->account_id,
            'subTotal' => (int) $cart->sub_total,
            'sub_total' => (int) $cart->sub_total,
            'discountAmount' => (int) $cart->discount_amount,
            'discount_amount' => (int) $cart->discount_amount,
            'total' => (int) $cart->total,
            'couponCode' => $cart->coupon?->code,
            'coupon_code' => $cart->coupon?->code,
            'items' => $cart->items->map(function (CartItem $item) {
                $variant = $item->variant;
                $product = $variant?->product;
                $image = $variant?->images?->first()?->image_url
                    ?: $product?->images?->sortByDesc('is_primary')->sortBy('sort_order')->first()?->image_url;

                return [
                    'id' => $item->id,
                    'variantId' => $item->product_variant_id,
                    'product_variant_id' => $item->product_variant_id,
                    'sku' => $variant?->sku,
                    'productName' => $product?->name,
                    'product_name' => $product?->name,
                    'productSlug' => $product?->slug,
                    'product_slug' => $product?->slug,
                    'size' => $variant?->size,
                    'color' => $variant?->color,
                    'imageUrl' => $image,
                    'image_url' => $image,
                    'quantity' => (int) $item->quantity,
                    'price' => (int) $item->price,
                    'subTotal' => (int) $item->price * (int) $item->quantity,
                    'sub_total' => (int) $item->price * (int) $item->quantity,
                ];
            })->values(),
        ];
    }
}
