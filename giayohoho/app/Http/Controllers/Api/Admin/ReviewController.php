<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $size = (int) $request->input('size', 10);
        $page = $request->has('page') ? ((int) $request->input('page')) + 1 : null;
        $q = $request->string('q')->toString();
        $status = $request->string('status')->toString();
        $rating = $request->input('rating');

        $reviews = Review::with(['user', 'product', 'order', 'moderator'])
            ->when($status, fn ($query) => $query->where('status', strtoupper($status)))
            ->when($rating, fn ($query) => $query->where('rating', (int) $rating))
            ->when($q, function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('comment', 'like', "%{$q}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->where('username', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%"))
                        ->orWhereHas('product', fn ($productQuery) => $productQuery
                            ->where('name', 'like', "%{$q}%")
                            ->orWhere('slug', 'like', "%{$q}%"));
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($size, ['*'], 'page', $page);

        return response()->json([
            'content' => $reviews->getCollection()->map(fn (Review $review) => $this->payload($review))->values(),
            'page' => max(0, $reviews->currentPage() - 1),
            'size' => $reviews->perPage(),
            'totalElements' => $reviews->total(),
            'totalPages' => $reviews->lastPage(),
            'last' => $reviews->currentPage() >= $reviews->lastPage(),
            'first' => $reviews->currentPage() === 1,
        ]);
    }

    public function approve(Request $request, int $id)
    {
        return $this->moderate($request, $id, 'APPROVED', 'Đã duyệt đánh giá');
    }

    public function reject(Request $request, int $id)
    {
        return $this->moderate($request, $id, 'REJECTED', 'Đã từ chối đánh giá');
    }

    public function reply(Request $request, int $id)
    {
        $data = $request->validate([
            'shopReply' => 'nullable|string|max:2000',
            'shop_reply' => 'nullable|string|max:2000',
        ]);

        $review = Review::with(['user', 'product', 'order', 'moderator'])->findOrFail($id);
        $review->forceFill([
            'shop_reply' => trim((string) ($data['shopReply'] ?? $data['shop_reply'] ?? '')) ?: null,
            'moderated_by' => $request->user()->id,
            'moderated_at' => now(),
        ])->save();

        return $this->ok($this->payload($review->refresh()->load(['user', 'product', 'order', 'moderator'])), 'Đã lưu phản hồi');
    }

    public function destroy(int $id)
    {
        Review::findOrFail($id)->delete();
        return $this->ok(null, 'Đã xóa đánh giá');
    }

    private function moderate(Request $request, int $id, string $status, string $message)
    {
        $review = Review::with(['user', 'product', 'order', 'moderator'])->findOrFail($id);
        $review->forceFill([
            'status' => $status,
            'moderated_by' => $request->user()->id,
            'moderated_at' => now(),
        ])->save();

        return $this->ok($this->payload($review->refresh()->load(['user', 'product', 'order', 'moderator'])), $message);
    }

    private function payload(Review $review): array
    {
        $customerName = trim(($review->user?->first_name ?? '').' '.($review->user?->last_name ?? '')) ?: $review->user?->username;

        return [
            'id' => $review->id,
            'productId' => $review->product_id,
            'productName' => $review->product?->name,
            'productSlug' => $review->product?->slug,
            'orderId' => $review->order_id,
            'accountId' => $review->account_id,
            'username' => $review->user?->username,
            'customerName' => $customerName,
            'customerEmail' => $review->user?->email,
            'customerAvatar' => $review->user?->avatar,
            'rating' => (int) $review->rating,
            'comment' => $review->comment,
            'status' => $review->status,
            'shopReply' => $review->shop_reply,
            'createdAt' => $review->created_at?->toIso8601String(),
            'updatedAt' => $review->updated_at?->toIso8601String(),
            'moderatedAt' => $review->moderated_at?->toIso8601String(),
            'moderatedBy' => $review->moderator?->username,
        ];
    }
}
