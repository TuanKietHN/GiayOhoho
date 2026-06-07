<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSpecsShoes;
use App\Models\Surface;
use App\Models\Tag;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q');
        $size = (int) $request->input('size', 50);
        $page = $request->has('page') ? ((int) $request->input('page')) + 1 : null;
        $items = Product::with(['category', 'surfaces', 'tags', 'specs', 'variants', 'images'])
            ->when($q, function ($b) use ($q) {
                $b->where(function ($s) use ($q) {
                    $s->where('name', 'like', "%{$q}%")
                      ->orWhere('brand', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($size, ['*'], 'page', $page);
        $content = $items->getCollection()->map(fn(Product $product) => $this->payload($product))->values();

        return response()->json([
            'content' => $content,
            'data' => $content,
            'page' => max(0, $items->currentPage() - 1),
            'current_page' => $items->currentPage(),
            'size' => $items->perPage(),
            'per_page' => $items->perPage(),
            'totalElements' => $items->total(),
            'total' => $items->total(),
            'totalPages' => $items->lastPage(),
            'last_page' => $items->lastPage(),
            'last' => $items->currentPage() >= $items->lastPage(),
            'first' => $items->currentPage() === 1,
        ]);
    }

    public function show(int $id)
    {
        return response()->json($this->payload(Product::with(['category', 'surfaces', 'tags', 'specs', 'variants', 'images'])->findOrFail($id)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'nullable|integer|exists:categories,id',
            'categoryId' => 'nullable|integer|exists:categories,id',
            'name' => 'required|string',
            'slug' => 'required|string|unique:products,slug',
            'brand' => 'nullable|string',
            'gender' => 'nullable|string|max:30',
            'base_price' => 'required_without:basePrice|integer',
            'basePrice' => 'required_without:base_price|integer',
            'originalPrice' => 'nullable|integer',
            'description' => 'nullable|string',
            'surfaces' => 'array',
            'surfaces.*' => 'string',
            'tags' => 'array',
            'tags.*' => 'string',
            'specs' => 'array',
        ]);
        $data = $this->normalizeProductData($data);
        $product = Product::create($data);
        if (! empty($data['surfaces'])) {
            $surfaceIds = Surface::whereIn('code', $data['surfaces'])->pluck('id');
            $product->surfaces()->sync($surfaceIds);
        }
        if (! empty($data['tags'])) {
            $tagIds = Tag::whereIn('slug', $data['tags'])->pluck('id');
            $product->tags()->sync($tagIds);
        }
        if (! empty($data['specs'])) {
            ProductSpecsShoes::updateOrCreate(['product_id' => $product->id], $data['specs']);
        }
        return response()->json($this->payload($product->load(['category', 'surfaces', 'tags', 'specs', 'variants', 'images'])), 201);
    }

    public function update(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $data = $request->validate([
            'category_id' => 'nullable|integer|exists:categories,id',
            'categoryId' => 'nullable|integer|exists:categories,id',
            'name' => 'sometimes|required|string',
            'slug' => 'sometimes|required|string|unique:products,slug,'.$product->id,
            'brand' => 'nullable|string',
            'gender' => 'nullable|string|max:30',
            'base_price' => 'nullable|integer',
            'basePrice' => 'nullable|integer',
            'originalPrice' => 'nullable|integer',
            'description' => 'nullable|string',
            'surfaces' => 'array',
            'surfaces.*' => 'string',
            'tags' => 'array',
            'tags.*' => 'string',
            'specs' => 'array',
        ]);
        $data = $this->normalizeProductData($data);
        $product->update($data);
        if ($request->has('surfaces')) {
            $surfaceIds = Surface::whereIn('code', $data['surfaces'] ?? [])->pluck('id');
            $product->surfaces()->sync($surfaceIds);
        }
        if ($request->has('tags')) {
            $tagIds = Tag::whereIn('slug', $data['tags'] ?? [])->pluck('id');
            $product->tags()->sync($tagIds);
        }
        if ($request->has('specs')) {
            ProductSpecsShoes::updateOrCreate(['product_id' => $product->id], $data['specs'] ?? []);
        }
        return response()->json($this->payload($product->load(['category', 'surfaces', 'tags', 'specs', 'variants', 'images'])));
    }

    public function destroy(int $id)
    {
        Product::findOrFail($id)->forceFill(['deleted_at' => now()])->save();
        return response()->json(['message' => 'deleted']);
    }

    public function restore(int $id)
    {
        Product::findOrFail($id)->forceFill(['deleted_at' => null])->save();
        return response()->json(['message' => 'restored']);
    }

    public function updateSalePrice(Request $request, int $id)
    {
        $data = $request->validate([
            'currentPrice' => 'required|integer|min:0',
            'originalPrice' => 'nullable|integer|min:0',
        ]);
        $product = Product::findOrFail($id);
        $product->forceFill([
            'base_price' => $data['currentPrice'],
            'original_price' => $data['originalPrice'] ?? null,
        ])->save();

        return response()->json($this->payload($product->refresh()->load(['category', 'surfaces', 'tags', 'specs', 'variants', 'images'])));
    }

    public function storeVariant(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $data = $request->validate([
            'size' => 'required|string|max:50',
            'color' => 'required|string|max:80',
            'stock' => 'required|integer|min:0',
            'price' => 'nullable|integer|min:0',
            'sku' => 'nullable|string|max:100',
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'size' => $data['size'],
            'color' => $data['color'],
            'stock' => $data['stock'],
            'sku' => $data['sku'] ?: strtoupper($product->slug.'-'.$data['size'].'-'.$data['color']),
            'extra_price' => isset($data['price']) ? max(0, (int) $data['price'] - (int) $product->base_price) : 0,
        ]);

        return response()->json($this->payload($product->refresh()->load(['category', 'surfaces', 'tags', 'specs', 'variants', 'images'])), 201);
    }

    public function updateVariant(Request $request, int $id, int $variantId)
    {
        $product = Product::findOrFail($id);
        $variant = ProductVariant::where('product_id', $product->id)->findOrFail($variantId);
        $data = $request->validate([
            'size' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:80',
            'stock' => 'nullable|integer|min:0',
            'price' => 'nullable|integer|min:0',
            'sku' => 'nullable|string|max:100',
        ]);
        $variant->fill([
            'size' => $data['size'] ?? $variant->size,
            'color' => $data['color'] ?? $variant->color,
            'stock' => $data['stock'] ?? $variant->stock,
            'sku' => $data['sku'] ?? $variant->sku,
            'extra_price' => isset($data['price']) ? max(0, (int) $data['price'] - (int) $product->base_price) : $variant->extra_price,
        ])->save();

        return response()->json($this->payload($product->refresh()->load(['category', 'surfaces', 'tags', 'specs', 'variants', 'images'])));
    }

    public function destroyVariant(int $id, int $variantId)
    {
        ProductVariant::where('product_id', $id)->findOrFail($variantId)->forceFill(['deleted_at' => now()])->save();
        return response()->json(['message' => 'deleted']);
    }

    public function restoreVariant(int $id, int $variantId)
    {
        ProductVariant::where('product_id', $id)->findOrFail($variantId)->forceFill(['deleted_at' => null])->save();
        return response()->json(['message' => 'restored']);
    }

    public function images(int $id)
    {
        Product::findOrFail($id);
        return response()->json(ProductImage::where('product_id', $id)->orderBy('sort_order')->get()->map(fn(ProductImage $image) => $this->imagePayload($image))->values());
    }

    public function uploadImage(Request $request, int $id)
    {
        Product::findOrFail($id);
        $data = $request->validate([
            'file' => 'nullable|image|max:4096',
            'imageUrl' => 'nullable|string|max:512',
            'variantId' => 'nullable|integer|exists:product_variants,id',
            'altText' => 'nullable|string|max:255',
        ]);
        $url = $data['imageUrl'] ?? null;
        if ($request->hasFile('file')) {
            $url = Storage::url($request->file('file')->store('products', 'public'));
        }
        if (! $url) {
            return response()->json(['message' => 'image_required'], 422);
        }
        $image = ProductImage::create([
            'product_id' => $id,
            'product_variant_id' => $data['variantId'] ?? null,
            'image_url' => $url,
            'alt_text' => $data['altText'] ?? null,
            'sort_order' => (int) ProductImage::where('product_id', $id)->max('sort_order') + 1,
            'is_primary' => ! ProductImage::where('product_id', $id)->where('is_primary', true)->exists(),
        ]);

        return response()->json($this->imagePayload($image), 201);
    }

    public function updateImage(Request $request, int $id, int $imageId)
    {
        $image = ProductImage::where('product_id', $id)->findOrFail($imageId);
        $data = $request->validate([
            'variantId' => 'nullable|integer|exists:product_variants,id',
            'altText' => 'nullable|string|max:255',
            'sortOrder' => 'nullable|integer|min:0',
            'primary' => 'nullable|boolean',
        ]);
        if (array_key_exists('primary', $data) && $data['primary']) {
            ProductImage::where('product_id', $id)->update(['is_primary' => false]);
        }
        $image->forceFill([
            'product_variant_id' => $data['variantId'] ?? $image->product_variant_id,
            'alt_text' => $data['altText'] ?? $image->alt_text,
            'sort_order' => $data['sortOrder'] ?? $image->sort_order,
            'is_primary' => array_key_exists('primary', $data) ? (bool) $data['primary'] : $image->is_primary,
        ])->save();

        return response()->json($this->imagePayload($image->refresh()));
    }

    public function destroyImage(int $id, int $imageId)
    {
        ProductImage::where('product_id', $id)->findOrFail($imageId)->forceFill(['deleted_at' => now()])->save();
        return response()->json(['message' => 'deleted']);
    }

    public function restoreImage(int $id, int $imageId)
    {
        ProductImage::where('product_id', $id)->findOrFail($imageId)->forceFill(['deleted_at' => null])->save();
        return response()->json($this->imagePayload(ProductImage::where('product_id', $id)->findOrFail($imageId)));
    }

    public function setPrimaryImage(int $id, int $imageId)
    {
        ProductImage::where('product_id', $id)->update(['is_primary' => false]);
        $image = ProductImage::where('product_id', $id)->findOrFail($imageId);
        $image->forceFill(['is_primary' => true])->save();
        return response()->json($this->imagePayload($image->refresh()));
    }

    public function reorderImages(Request $request, int $id)
    {
        $data = $request->validate([
            'items' => 'required|array',
            'items.*.imageId' => 'required|integer',
            'items.*.sortOrder' => 'required|integer',
        ]);
        foreach ($data['items'] as $item) {
            ProductImage::where('product_id', $id)->where('id', $item['imageId'])->update(['sort_order' => $item['sortOrder']]);
        }

        return $this->images($id);
    }

    public function bulkAssignCategory(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'filters' => 'array'
        ]);
        $q = Product::query();
        $filters = $data['filters'] ?? [];
        if (!empty($filters['brand'])) $q->whereIn('brand', (array) $filters['brand']);
        if (!empty($filters['gender'])) $q->whereIn('gender', (array) $filters['gender']);
        if (!empty($filters['min_price'])) $q->where('base_price', '>=', (int) $filters['min_price']);
        if (!empty($filters['max_price'])) $q->where('base_price', '<=', (int) $filters['max_price']);
        if (!empty($filters['surface'])) {
            $q->whereHas('surfaces', function ($b) use ($filters) { $b->whereIn('code', (array) $filters['surface']); });
        }
        if (!empty($filters['cushioning_level']) || !empty($filters['pronation_type'])) {
            $q->whereHas('specs', function ($b) use ($filters) {
                if (!empty($filters['cushioning_level'])) $b->whereIn('cushioning_level', (array) $filters['cushioning_level']);
                if (!empty($filters['pronation_type'])) $b->whereIn('pronation_type', (array) $filters['pronation_type']);
            });
        }
        $count = $q->update(['category_id' => (int) $data['category_id']]);
        return response()->json(['updated' => $count]);
    }

    private function normalizeProductData(array $data): array
    {
        if (array_key_exists('categoryId', $data)) {
            $data['category_id'] = $data['categoryId'];
        }
        if (array_key_exists('basePrice', $data)) {
            $data['base_price'] = $data['basePrice'];
        }
        if (array_key_exists('originalPrice', $data)) {
            $data['original_price'] = $data['originalPrice'];
        }
        unset($data['categoryId'], $data['basePrice'], $data['originalPrice']);

        return $data;
    }

    private function payload(Product $product): array
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
            'categoryId' => $product->category_id,
            'category_id' => $product->category_id,
            'categoryName' => $product->category?->name,
            'category_name' => $product->category?->name,
            'description' => $product->description,
            'createdAt' => $product->created_at?->toIso8601String(),
            'created_at' => $product->created_at?->toIso8601String(),
            'deletedAt' => $product->deleted_at,
            'deleted_at' => $product->deleted_at,
            'images' => $product->images->map(fn(ProductImage $image) => $this->imagePayload($image))->values(),
            'surfaces' => $product->surfaces->pluck('code')->values(),
            'tags' => $product->tags->pluck('slug')->values(),
            'variants' => $product->variants->map(fn(ProductVariant $variant) => $this->variantPayload($variant, $product))->values(),
        ];
    }

    private function variantPayload(ProductVariant $variant, ?Product $product = null): array
    {
        $product ??= $variant->product;
        return [
            'id' => $variant->id,
            'size' => $variant->size,
            'color' => $variant->color,
            'sku' => $variant->sku,
            'stock' => (int) $variant->stock,
            'extraPrice' => (int) $variant->extra_price,
            'extra_price' => (int) $variant->extra_price,
            'finalPrice' => (int) ($product?->base_price ?? 0) + (int) $variant->extra_price,
            'final_price' => (int) ($product?->base_price ?? 0) + (int) $variant->extra_price,
        ];
    }

    private function imagePayload(ProductImage $image): array
    {
        return [
            'id' => $image->id,
            'imageUrl' => $image->image_url,
            'image_url' => $image->image_url,
            'altText' => $image->alt_text,
            'alt_text' => $image->alt_text,
            'variantId' => $image->product_variant_id,
            'product_variant_id' => $image->product_variant_id,
            'primary' => (bool) $image->is_primary,
            'is_primary' => (bool) $image->is_primary,
            'sortOrder' => (int) $image->sort_order,
            'sort_order' => (int) $image->sort_order,
            'contentType' => $image->content_type,
            'content_type' => $image->content_type,
            'sizeBytes' => $image->size_bytes,
            'size_bytes' => $image->size_bytes,
            'deletedAt' => $image->deleted_at,
            'deleted_at' => $image->deleted_at,
        ];
    }
}
