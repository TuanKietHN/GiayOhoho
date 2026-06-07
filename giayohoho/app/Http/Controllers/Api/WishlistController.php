<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $items = Wishlist::with('product.images', 'product.variants')
            ->where('account_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->get();
        if ($request->is('api/auth/*')) {
            return response()->json($items);
        }

        $products = $items->pluck('product')->filter()->map(fn(Product $product) => $this->productPayload($product))->values();
        return response()->json($products);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'nullable|integer|exists:products,id',
            'productId' => 'nullable|integer|exists:products,id',
        ]);
        $productId = (int) ($data['productId'] ?? $data['product_id'] ?? 0);
        if (! $productId) {
            return response()->json(['message' => 'product_required'], 422);
        }
        $existing = Wishlist::where('account_id', $request->user()->id)
            ->where('product_id', $productId)
            ->first();

        if ($existing && ! $request->is('api/auth/*')) {
            $existing->delete();
            return response()->json(['message' => 'removed']);
        }

        $item = Wishlist::firstOrCreate([
            'account_id' => $request->user()->id,
            'product_id' => $productId,
        ], ['deleted_at' => null]);
        if ($item->deleted_at) {
            $item->forceFill(['deleted_at' => null])->save();
        }
        return response()->json($item, 201);
    }

    public function destroy(Request $request, int $id)
    {
        $item = Wishlist::where('account_id', $request->user()->id)->findOrFail($id);
        $item->delete();
        return response()->json(['message' => 'deleted']);
    }

    private function productPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'brand' => $product->brand,
            'gender' => $product->gender,
            'basePrice' => (int) $product->base_price,
            'base_price' => (int) $product->base_price,
            'originalPrice' => $product->original_price ? (int) $product->original_price : null,
            'original_price' => $product->original_price ? (int) $product->original_price : null,
            'description' => $product->description,
            'variants' => $product->variants->map(fn($variant) => [
                'id' => $variant->id,
                'size' => $variant->size,
                'color' => $variant->color,
                'sku' => $variant->sku,
                'stock' => (int) $variant->stock,
                'extraPrice' => (int) $variant->extra_price,
                'extra_price' => (int) $variant->extra_price,
                'finalPrice' => (int) $product->base_price + (int) $variant->extra_price,
                'final_price' => (int) $product->base_price + (int) $variant->extra_price,
            ])->values(),
            'images' => $product->images->map(fn($image) => [
                'id' => $image->id,
                'imageUrl' => $image->image_url,
                'image_url' => $image->image_url,
                'altText' => $image->alt_text,
                'alt_text' => $image->alt_text,
                'primary' => (bool) $image->is_primary,
                'is_primary' => (bool) $image->is_primary,
                'deletedAt' => $image->deleted_at,
                'deleted_at' => $image->deleted_at,
            ])->values(),
        ];
    }
}
