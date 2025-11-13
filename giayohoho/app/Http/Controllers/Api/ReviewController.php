<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function listByProduct(int $productId)
    {
        $reviews = Review::with('user')->where('product_id', $productId)->orderByDesc('id')->get();
        return response()->json($reviews);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $userId = $request->user()->id;
        $variantIds = ProductVariant::where('product_id', $data['product_id'])->pluck('id');
        $purchased = OrderItem::whereIn('product_variant_id', $variantIds)
            ->whereHas('order', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->exists();

        if (! $purchased) {
            return response()->json(['message' => 'only_purchased'], 422);
        }

        $review = Review::create([
            'user_id' => $userId,
            'product_id' => $data['product_id'],
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
        ]);
        return response()->json($review, 201);
    }

    public function update(Request $request, int $id)
    {
        $review = Review::where('user_id', $request->user()->id)->findOrFail($id);
        $data = $request->validate([
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);
        $review->update($data);
        return response()->json($review);
    }

    public function destroy(Request $request, int $id)
    {
        $review = Review::where('user_id', $request->user()->id)->findOrFail($id);
        $review->delete();
        return response()->json(['message' => 'deleted']);
    }
}
