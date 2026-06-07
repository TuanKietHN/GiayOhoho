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
            'orderAddress' => 'nullable|string|max:500',
            'recipientName' => 'nullable|string|max:255',
            'recipientPhone' => 'nullable|string|max:30',
            'contactEmail' => 'nullable|email|max:255',
            'addressLine' => 'nullable|string|max:255',
            'provinceId' => 'nullable|integer',
            'districtId' => 'nullable|integer',
            'wardCode' => 'nullable|string|max:50',
            'shippingProvider' => 'nullable|string|max:50',
            'shippingFee' => 'nullable|numeric|min:0',
            'shippingDiscount' => 'nullable|numeric|min:0',
            'shippingServiceId' => 'nullable|integer',
            'shippingServiceTypeId' => 'nullable|integer',
            'shippingQuoteId' => 'nullable|string|max:100',
            'payment_method' => 'nullable|string',
            'paymentMethod' => 'nullable|string',
            'coupon_code' => 'nullable|string',
            'couponCode' => 'nullable|string',
            'requestId' => 'nullable|string|max:100',
            'selectedCartItemIds' => 'nullable|array',
            'selectedCartItemIds.*' => 'integer',
        ]);
        $paymentMethod = $data['paymentMethod'] ?? $data['payment_method'] ?? null;
        if (! $paymentMethod) {
            return response()->json(['message' => 'payment_method_required'], 422);
        }
        $couponCode = $data['couponCode'] ?? $data['coupon_code'] ?? null;

        $accountId = $request->user()->id;
        $cart = Cart::with(['items.variant.product', 'coupon'])->where('account_id', $accountId)->first();
        if (! $cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'empty_cart'], 422);
        }

        $coupon = null;
        if (! empty($couponCode)) {
            $coupon = Coupon::where('code', $couponCode)->first();
            if ($coupon) {
                $cart->coupon_id = $coupon->id;
            }
        }

        if (! empty($data['selectedCartItemIds'])) {
            $selected = collect($data['selectedCartItemIds'])->map(fn($id) => (int) $id)->all();
            $cart->setRelation('items', $cart->items->whereIn('id', $selected)->values());
            if ($cart->items->isEmpty()) {
                return response()->json(['message' => 'empty_cart_selection'], 422);
            }
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

        $orderAddress = $data['orderAddress'] ?? $data['order_address'] ?? null;
        if (isset($data['address_id']) && $data['address_id']) {
            $orderAddress = $request->user()->addresses()->find($data['address_id'])?->address_line;
        }

        $shippingFee = (int) ($data['shippingFee'] ?? 0);
        $shippingDiscount = (int) ($data['shippingDiscount'] ?? 0);
        $grandTotal = max(0, $total + $shippingFee - $shippingDiscount);

        return DB::transaction(function () use ($accountId, $sub, $discount, $grandTotal, $coupon, $orderAddress, $cart, $request, $data, $paymentMethod, $shippingFee, $shippingDiscount) {
            $order = OrderDetail::create([
                'account_id' => $accountId,
                'total' => $grandTotal,
                'sub_total' => $sub,
                'discount_amount' => $discount,
                'coupon_id' => $coupon?->id,
                'order_address' => $orderAddress,
                'recipient_name' => $data['recipientName'] ?? null,
                'recipient_phone' => $data['recipientPhone'] ?? null,
                'contact_email' => $data['contactEmail'] ?? null,
                'payment_method' => strtoupper($paymentMethod),
                'shipping_provider' => $data['shippingProvider'] ?? null,
                'shipping_fee' => $shippingFee,
                'shipping_discount' => $shippingDiscount,
                'shipping_service_id' => $data['shippingServiceId'] ?? null,
                'shipping_service_type_id' => $data['shippingServiceTypeId'] ?? null,
                'shipping_quote_id' => $data['shippingQuoteId'] ?? null,
                'ghn_to_province_id' => $data['provinceId'] ?? null,
                'ghn_to_district_id' => $data['districtId'] ?? null,
                'ghn_to_ward_code' => $data['wardCode'] ?? null,
                'idempotency_key' => $data['requestId'] ?? null,
                'status' => 'PENDING',
            ]);

            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ]);
                ProductVariant::where('id', $item->product_variant_id)->decrement('stock', (int) $item->quantity);
            }

            if ($coupon) {
                UserCoupon::create([
                    'account_id' => $accountId,
                    'coupon_id' => $coupon->id,
                    'order_id' => $order->id,
                    'used_at' => Carbon::now(),
                ]);
                $coupon->increment('times_used');
            }

            Cart::where('id', $cart->id)->delete();

            return response()->json($this->orderPayload($order->load(['items.variant.product.images', 'coupon'])), 201);
        });
    }

    public function listOrders(Request $request)
    {
        $orders = OrderDetail::with(['items.variant.product.images', 'coupon'])->where('account_id', $request->user()->id)->orderByDesc('id')->get();
        return response()->json($orders->map(fn(OrderDetail $order) => $this->orderPayload($order))->values());
    }

    public function show(Request $request, int $id)
    {
        $order = OrderDetail::with(['items.variant.product.images', 'coupon'])->where('account_id', $request->user()->id)->findOrFail($id);
        return response()->json($this->orderPayload($order));
    }

    public function cancel(Request $request, int $id)
    {
        $order = OrderDetail::where('account_id', $request->user()->id)->findOrFail($id);
        if (! in_array($order->status, ['PENDING', 'CONFIRMED'], true)) {
            return response()->json(['message' => 'order_not_cancelable'], 422);
        }

        $order->status = 'CANCELLED';
        $order->save();

        return response()->json($this->orderPayload($order->load(['items.variant.product.images', 'coupon'])));
    }

    private function orderPayload(OrderDetail $order): array
    {
        return [
            'id' => $order->id,
            'total' => (int) $order->total,
            'subTotal' => (int) $order->sub_total,
            'sub_total' => (int) $order->sub_total,
            'discountAmount' => (int) $order->discount_amount,
            'discount_amount' => (int) $order->discount_amount,
            'status' => $order->status,
            'orderAddress' => $order->order_address,
            'order_address' => $order->order_address,
            'recipientName' => $order->recipient_name,
            'recipient_name' => $order->recipient_name,
            'recipientPhone' => $order->recipient_phone,
            'recipient_phone' => $order->recipient_phone,
            'contactEmail' => $order->contact_email,
            'contact_email' => $order->contact_email,
            'customerName' => $order->recipient_name,
            'phone' => $order->recipient_phone,
            'address' => $order->order_address,
            'paymentMethod' => $order->payment_method,
            'payment_method' => $order->payment_method,
            'couponCode' => $order->coupon?->code,
            'coupon_code' => $order->coupon?->code,
            'shippingProvider' => $order->shipping_provider,
            'shipping_provider' => $order->shipping_provider,
            'shippingFee' => (int) $order->shipping_fee,
            'shipping_fee' => (int) $order->shipping_fee,
            'shippingDiscount' => (int) $order->shipping_discount,
            'shipping_discount' => (int) $order->shipping_discount,
            'shippingServiceId' => $order->shipping_service_id,
            'shipping_service_id' => $order->shipping_service_id,
            'shippingServiceTypeId' => $order->shipping_service_type_id,
            'shipping_service_type_id' => $order->shipping_service_type_id,
            'shippingQuoteId' => $order->shipping_quote_id,
            'shipping_quote_id' => $order->shipping_quote_id,
            'ghnToProvinceId' => $order->ghn_to_province_id,
            'ghn_to_province_id' => $order->ghn_to_province_id,
            'ghnToDistrictId' => $order->ghn_to_district_id,
            'ghn_to_district_id' => $order->ghn_to_district_id,
            'ghnToWardCode' => $order->ghn_to_ward_code,
            'ghn_to_ward_code' => $order->ghn_to_ward_code,
            'expectedDeliveryTime' => $order->expected_delivery_time,
            'expected_delivery_time' => $order->expected_delivery_time,
            'createdAt' => $order->created_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'items' => $order->items->map(function (OrderItem $item) {
                $variant = $item->variant;
                $product = $variant?->product;
                $image = $product?->images?->sortByDesc('is_primary')->sortBy('sort_order')->first()?->image_url;

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
                    'variantSize' => $variant?->size,
                    'variantColor' => $variant?->color,
                    'imageUrl' => $image,
                    'image_url' => $image,
                    'productImage' => $image,
                    'quantity' => (int) $item->quantity,
                    'price' => (int) $item->price,
                    'subTotal' => (int) $item->price * (int) $item->quantity,
                    'sub_total' => (int) $item->price * (int) $item->quantity,
                ];
            })->values(),
        ];
    }
}
