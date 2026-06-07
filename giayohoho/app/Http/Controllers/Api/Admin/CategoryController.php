<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(Category::with('children')->orderBy('name')->get()->map(fn(Category $category) => $this->payload($category))->values());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'parent_id' => 'nullable|integer|exists:categories,id',
            'parentId' => 'nullable|integer|exists:categories,id',
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:categories,slug',
            'description' => 'nullable|string',
        ]);
        $data = $this->normalize($data);
        $cat = Category::create($data);
        return response()->json($this->payload($cat), 201);
    }

    public function update(Request $request, int $id)
    {
        $cat = Category::findOrFail($id);
        $data = $request->validate([
            'parent_id' => 'nullable|integer|exists:categories,id',
            'parentId' => 'nullable|integer|exists:categories,id',
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:categories,slug,'.$cat->id,
            'description' => 'nullable|string',
        ]);
        $data = $this->normalize($data);
        $cat->update($data);
        return response()->json($this->payload($cat->refresh()));
    }

    public function destroy(int $id)
    {
        Category::findOrFail($id)->forceFill(['deleted_at' => now()])->save();
        return response()->json(['message' => 'deleted']);
    }

    public function restore(int $id)
    {
        Category::findOrFail($id)->forceFill(['deleted_at' => null])->save();
        return response()->json(['message' => 'restored']);
    }

    public function products(int $id)
    {
        Category::findOrFail($id);
        return response()->json(Product::with(['images', 'variants'])->where('category_id', $id)->get()->map(fn(Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'brand' => $product->brand,
            'basePrice' => (int) $product->base_price,
            'images' => $product->images->map(fn($image) => [
                'id' => $image->id,
                'imageUrl' => $image->image_url,
                'primary' => (bool) $image->is_primary,
                'deletedAt' => $image->deleted_at,
            ])->values(),
            'variants' => $product->variants->map(fn($variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'size' => $variant->size,
                'color' => $variant->color,
                'stock' => (int) $variant->stock,
                'finalPrice' => (int) $product->base_price + (int) $variant->extra_price,
            ])->values(),
        ])->values());
    }

    public function addProduct(int $id, int $productId)
    {
        $product = Product::findOrFail($productId);
        $product->forceFill(['category_id' => $id])->save();

        return response()->json(['id' => $product->id, 'categoryId' => $product->category_id]);
    }

    public function removeProduct(int $id, int $productId)
    {
        $product = Product::where('category_id', $id)->findOrFail($productId);
        $product->forceFill(['category_id' => null])->save();

        return response()->json(['id' => $product->id, 'categoryId' => null]);
    }

    private function normalize(array $data): array
    {
        if (array_key_exists('parentId', $data)) {
            $data['parent_id'] = $data['parentId'];
        }
        unset($data['parentId']);

        return $data;
    }

    private function payload(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'parentId' => $category->parent_id,
            'parent_id' => $category->parent_id,
            'parentName' => $category->parent?->name,
            'children' => $category->relationLoaded('children')
                ? $category->children->map(fn(Category $child) => $this->payload($child))->values()
                : null,
            'createdAt' => $category->created_at?->toIso8601String(),
            'deletedAt' => $category->deleted_at,
        ];
    }
}
