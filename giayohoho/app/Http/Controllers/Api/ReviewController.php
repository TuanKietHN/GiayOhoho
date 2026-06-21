<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\OrderDetail;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function listByProduct(int $productId)
    {
        $reviews = Review::with('user')
            ->where('product_id', $productId)
            ->where('status', 'APPROVED')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Review $review) => $this->payload($review))
            ->values();

        return $this->ok($reviews);
    }

    public function options(Request $request)
    {
        $data = $request->validate([
            'productId' => 'required_without:product_id|integer|exists:products,id',
            'product_id' => 'required_without:productId|integer|exists:products,id',
        ]);
        $productId = (int) ($data['productId'] ?? $data['product_id']);
        $accountId = $request->user()->id;

        $variantIds = ProductVariant::where('product_id', $productId)->pluck('id');
        $orders = OrderDetail::query()
            ->with('items.variant')
            ->where('account_id', $accountId)
            ->whereIn('status', ['DELIVERED', 'DONE'])
            ->whereHas('items', fn ($query) => $query->whereIn('product_variant_id', $variantIds))
            ->orderByDesc('created_at')
            ->get()
            ->reject(fn (OrderDetail $order) => Review::where('account_id', $accountId)
                ->where('product_id', $productId)
                ->where('order_id', $order->id)
                ->exists())
            ->map(function (OrderDetail $order) use ($variantIds) {
                $item = $order->items->first(fn ($item) => $variantIds->contains($item->product_variant_id));

                return [
                    'orderId' => $order->id,
                    'purchasedAt' => $order->created_at?->toIso8601String(),
                    'variantSize' => $item?->variant?->size,
                    'variantColor' => $item?->variant?->color,
                    'quantity' => (int) ($item?->quantity ?? 0),
                ];
            })
            ->values();

        return $this->ok($orders);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required_without:productId|integer|exists:products,id',
            'productId' => 'required_without:product_id|integer|exists:products,id',
            'order_id' => 'nullable|integer|exists:order_details,id',
            'orderId' => 'nullable|integer|exists:order_details,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:10|max:1000',
        ]);

        $userId = $request->user()->id;
        $productId = (int) ($data['productId'] ?? $data['product_id']);
        $orderId = $data['orderId'] ?? $data['order_id'] ?? null;
        $variantIds = ProductVariant::where('product_id', $productId)->pluck('id');
        $order = $this->reviewableOrder($userId, $productId, $variantIds, $orderId ? (int) $orderId : null);

        if (! $order) {
            return response()->json(['message' => 'only_delivered_order_can_be_reviewed'], 422);
        }

        $alreadyReviewed = Review::where('account_id', $userId)
            ->where('product_id', $productId)
            ->where('order_id', $order->id)
            ->exists();
        if ($alreadyReviewed) {
            return response()->json(['message' => 'order_already_reviewed_for_product'], 422);
        }

        $review = Review::create([
            'account_id' => $userId,
            'product_id' => $productId,
            'order_id' => $order->id,
            'rating' => $data['rating'],
            'comment' => trim($data['comment']),
            'status' => 'PENDING',
        ]);

        return $this->created($this->payload($review->load('user')));
    }

    public function update(Request $request, int $id)
    {
        $review = Review::where('account_id', $request->user()->id)->findOrFail($id);
        $data = $request->validate([
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);
        $review->update($data);
        return $this->ok($this->payload($review->refresh()->load('user')));
    }

    public function destroy(Request $request, int $id)
    {
        $review = Review::where('account_id', $request->user()->id)->findOrFail($id);
        $review->delete();
        return $this->ok(null, 'Review deleted');
    }

    private function reviewableOrder(int $accountId, int $productId, $variantIds, ?int $orderId): ?OrderDetail
    {
        return OrderDetail::query()
            ->where('account_id', $accountId)
            ->whereIn('status', ['DELIVERED', 'DONE'])
            ->whereHas('items', fn ($query) => $query->whereIn('product_variant_id', $variantIds))
            ->when($orderId, fn ($query) => $query->where('id', $orderId))
            ->orderByDesc('created_at')
            ->first();
    }

    private function payload(Review $review): array
    {
        return [
            'id' => $review->id,
            'username' => $review->user?->username,
            'userAvatar' => $review->user?->avatar,
            'rating' => (int) $review->rating,
            'comment' => $review->comment,
            'shopReply' => $review->shop_reply,
            'createdAt' => $review->created_at?->toIso8601String(),
        ];
    }
}
