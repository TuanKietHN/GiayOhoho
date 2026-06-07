<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class ProductController extends Controller
{
    // GET /api/products
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['category', 'variants', 'images', 'surfaces', 'specs'])
            ->withAvg('reviews as avg_rating', 'rating')
            ->withCount('reviews');

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(function (Builder $b) use ($q) {
                $b->where('name', 'like', "%{$q}%")
                  ->orWhere('brand', 'like', "%{$q}%");
            });
        }

        if ($request->filled('brand')) {
            $query->whereIn('brand', (array) $request->input('brand'));
        }

        if ($request->filled('category')) {
            $category = $request->string('category')->toString();
            $query->whereHas('category', fn(Builder $b) => $b->where('slug', $category)->orWhere('name', $category));
        }

        if ($request->filled('gender')) {
            $query->whereIn('gender', (array) $request->input('gender'));
        }

        $minPrice = $request->input('minPrice', $request->input('min_price'));
        $maxPrice = $request->input('maxPrice', $request->input('max_price'));
        if ($minPrice !== null && $minPrice !== '') {
            $query->where('base_price', '>=', (int) $minPrice);
        }
        if ($maxPrice !== null && $maxPrice !== '') {
            $query->where('base_price', '<=', (int) $maxPrice);
        }

        if ($request->boolean('sale')) {
            $query->whereNotNull('original_price')->whereColumn('original_price', '>', 'base_price');
        }

        if ($request->filled('size') || $request->filled('color')) {
            $query->whereHas('variants', function (Builder $b) use ($request) {
                if ($request->filled('size')) {
                    $b->whereIn('size', (array) $request->input('size'));
                }
                if ($request->filled('color')) {
                    $b->whereIn('color', (array) $request->input('color'));
                }
                $b->where('stock', '>', 0);
            });
        }

        if ($request->filled('surface')) {
            $query->whereHas('surfaces', function (Builder $b) use ($request) {
                $b->whereIn('code', (array) $request->input('surface'));
            });
        }

        if ($request->filled('cushioning_level') || $request->filled('pronation_type') || $request->boolean('is_waterproof', null) !== null) {
            $query->whereHas('specs', function (Builder $b) use ($request) {
                if ($request->filled('cushioning_level')) {
                    $b->whereIn('cushioning_level', (array) $request->input('cushioning_level'));
                }
                if ($request->filled('pronation_type')) {
                    $b->whereIn('pronation_type', (array) $request->input('pronation_type'));
                }
                if ($request->has('is_waterproof')) {
                    $b->where('is_waterproof', (bool) $request->input('is_waterproof'));
                }
            });
        }

        $sort = $request->string('sort')->toString();
        if ($sort === 'newest') {
            $query->orderByDesc('created_at');
        } elseif ($sort === 'price_asc') {
            $query->orderBy('base_price');
        } elseif ($sort === 'price_desc') {
            $query->orderByDesc('base_price');
        } elseif ($sort === 'rating_desc') {
            $query->orderByDesc('avg_rating');
        }

        $perPage = (int) ($request->input('perPage', $request->input('per_page', 12)));
        $page = $request->has('page') ? ((int) $request->input('page')) + 1 : null;
        $products = $query->paginate($perPage, ['*'], 'page', $page)->appends($request->query());
        $items = $products->getCollection()->map(fn(Product $product) => $this->productPayload($product))->values();

        return response()->json([
            'content' => $items,
            'data' => $items,
            'page' => max(0, $products->currentPage() - 1),
            'current_page' => $products->currentPage(),
            'size' => $products->perPage(),
            'per_page' => $products->perPage(),
            'totalElements' => $products->total(),
            'total' => $products->total(),
            'totalPages' => $products->lastPage(),
            'last_page' => $products->lastPage(),
            'last' => $products->currentPage() >= $products->lastPage(),
            'first' => $products->currentPage() === 1,
        ]);
    }

    // GET /api/products/{id}
    public function show($id)
    {
        $product = Product::with(['category', 'variants', 'images', 'surfaces', 'specs'])
            ->findOrFail($id);

        return response()->json($this->productPayload($product));
    }

    // GET /api/products/by-slug/{slug}
    public function showBySlug(string $slug)
    {
        $product = Product::with(['category', 'variants', 'images', 'surfaces', 'specs'])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json($this->productPayload($product));
    }

    // GET /api/products/{id}/similar
    public function similar($id)
    {
        $base = Product::with(['surfaces', 'specs'])->findOrFail($id);
        $surfaceCodes = $base->surfaces->pluck('code');
        $query = Product::query()
            ->where('id', '!=', $base->id)
            ->where(function (Builder $b) use ($base, $surfaceCodes) {
                $b->where('brand', $base->brand)
                  ->orWhereHas('surfaces', function (Builder $s) use ($surfaceCodes) {
                      $s->whereIn('code', $surfaceCodes);
                  })
                  ->orWhereHas('specs', function (Builder $p) use ($base) {
                      $p->where('cushioning_level', $base->specs?->cushioning_level);
                  });
            })
            ->whereBetween('base_price', [max(0, (int)$base->base_price - 1000000), (int)$base->base_price + 1000000])
            ->with(['images', 'variants'])
            ->orderByDesc('id')
            ->limit(12)
            ->get();
        return response()->json($query->map(fn(Product $product) => $this->productPayload($product))->values());
    }

    private function productPayload(Product $product): array
    {
        $variants = $product->variants->map(fn($variant) => [
            'id' => $variant->id,
            'size' => $variant->size,
            'color' => $variant->color,
            'sku' => $variant->sku,
            'stock' => (int) $variant->stock,
            'extraPrice' => (int) $variant->extra_price,
            'extra_price' => (int) $variant->extra_price,
            'finalPrice' => (int) $product->base_price + (int) $variant->extra_price,
            'final_price' => (int) $product->base_price + (int) $variant->extra_price,
        ])->values();

        $images = $product->images
            ->sortByDesc('is_primary')
            ->sortBy('sort_order')
            ->map(fn($image) => [
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
            ])->values();

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
            'categoryName' => $product->category?->name,
            'category_name' => $product->category?->name,
            'categoryId' => $product->category_id,
            'category_id' => $product->category_id,
            'variants' => $variants,
            'images' => $images,
            'specs' => $product->specs,
            'avgRating' => $product->avg_rating ? (float) $product->avg_rating : null,
            'avg_rating' => $product->avg_rating ? (float) $product->avg_rating : null,
            'reviewCount' => (int) ($product->reviews_count ?? 0),
            'reviews_count' => (int) ($product->reviews_count ?? 0),
            'tags' => $product->relationLoaded('tags') ? $product->tags->pluck('name')->values() : [],
            'availability' => $product->deleted_at ? 'DELETED' : ($variants->sum('stock') > 0 ? 'AVAILABLE' : 'OUT_OF_STOCK'),
        ];
    }
}
