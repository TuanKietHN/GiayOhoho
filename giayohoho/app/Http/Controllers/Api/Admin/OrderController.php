<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use App\Models\OrderItem;
use App\Models\ShippingOrder;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = strtoupper($request->string('status')->toString());
        $size = (int) $request->input('size', 50);
        $page = $request->has('page') ? ((int) $request->input('page')) + 1 : null;
        $orders = OrderDetail::with(['items.variant.product.images', 'user', 'coupon'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate($size, ['*'], 'page', $page);
        $items = $orders->getCollection()->map(fn(OrderDetail $order) => $this->payload($order))->values();

        return response()->json([
            'content' => $items,
            'data' => $items,
            'page' => max(0, $orders->currentPage() - 1),
            'current_page' => $orders->currentPage(),
            'size' => $orders->perPage(),
            'per_page' => $orders->perPage(),
            'totalElements' => $orders->total(),
            'total' => $orders->total(),
            'totalPages' => $orders->lastPage(),
            'last_page' => $orders->lastPage(),
            'last' => $orders->currentPage() >= $orders->lastPage(),
            'first' => $orders->currentPage() === 1,
        ]);
    }

    public function show(int $id)
    {
        $order = OrderDetail::with(['items.variant.product.images', 'user', 'coupon'])->findOrFail($id);
        return response()->json($this->payload($order));
    }

    public function updateStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|string',
        ]);
        $status = strtoupper($data['status']);
        if (! in_array($status, ['PENDING', 'PAID', 'SHIPPING', 'DONE', 'CANCEL', 'CANCELLED'], true)) {
            return response()->json(['message' => 'invalid_status'], 422);
        }

        $order = OrderDetail::findOrFail($id);
        $order->status = $status === 'CANCEL' ? 'CANCELLED' : $status;
        $order->save();
        return response()->json($this->payload($order->load(['items.variant.product.images', 'user', 'coupon'])));
    }

    private function payload(OrderDetail $order): array
    {
        $shipping = ShippingOrder::where('order_id', $order->id)->first();
        $customerName = trim(($order->user?->first_name ?? '').' '.($order->user?->last_name ?? ''));

        return [
            'id' => $order->id,
            'customerName' => $customerName ?: ($order->user?->username ?? $order->recipient_name ?? 'Khách hàng'),
            'customerEmail' => $order->user?->email ?? $order->contact_email,
            'total' => (int) $order->total,
            'subTotal' => (int) $order->sub_total,
            'discountAmount' => (int) $order->discount_amount,
            'status' => $order->status,
            'couponCode' => $order->coupon?->code,
            'orderAddress' => $order->order_address,
            'recipientName' => $order->recipient_name,
            'recipientPhone' => $order->recipient_phone,
            'contactEmail' => $order->contact_email,
            'paymentMethod' => $order->payment_method,
            'shippingProvider' => $order->shipping_provider,
            'shippingFee' => (int) $order->shipping_fee,
            'shippingDiscount' => (int) $order->shipping_discount,
            'shippingServiceId' => $order->shipping_service_id,
            'shippingServiceTypeId' => $order->shipping_service_type_id,
            'shippingQuoteId' => $order->shipping_quote_id,
            'ghnToProvinceId' => $order->ghn_to_province_id,
            'ghnToDistrictId' => $order->ghn_to_district_id,
            'ghnToWardCode' => $order->ghn_to_ward_code,
            'shippingOrderCode' => $shipping?->provider_order_code,
            'shippingStatus' => $shipping?->status_raw,
            'shippingReason' => $shipping?->reason_message,
            'expectedDeliveryTime' => $order->expected_delivery_time?->toIso8601String() ?: $shipping?->expected_delivery_time?->toIso8601String(),
            'createdAt' => $order->created_at?->toIso8601String(),
            'updatedAt' => $order->updated_at?->toIso8601String(),
            'items' => $order->items->map(fn(OrderItem $item) => $this->itemPayload($item))->values(),
        ];
    }

    private function itemPayload(OrderItem $item): array
    {
        $variant = $item->variant;
        $product = $variant?->product;
        $image = $product?->images?->sortByDesc('is_primary')->sortBy('sort_order')->first()?->image_url;

        return [
            'productName' => $product?->name,
            'variantSize' => $variant?->size,
            'variantColor' => $variant?->color,
            'imageUrl' => $image,
            'quantity' => (int) $item->quantity,
            'price' => (int) $item->price,
            'subTotal' => (int) $item->price * (int) $item->quantity,
        ];
    }
}
