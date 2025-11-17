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

        if ($request->filled('gender')) {
            $query->whereIn('gender', (array) $request->input('gender'));
        }

        if ($request->filled('min_price')) {
            $query->where('base_price', '>=', (int) $request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('base_price', '<=', (int) $request->input('max_price'));
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

        if ($request->filled('cushioning_level') || $request->filled('pronation_type') || $request->has('is_waterproof')) {
            $query->whereHas('specs', function (Builder $b) use ($request) {
                if ($request->filled('cushioning_level')) {
                    $b->whereIn('cushioning_level', (array) $request->input('cushioning_level'));
                }
                if ($request->filled('pronation_type')) {
                    $b->whereIn('pronation_type', (array) $request->input('pronation_type'));
                }
                if ($request->has('is_waterproof')) {
                    $b->where('is_waterproof', $request->boolean('is_waterproof'));
                }
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
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

        $perPage = (int) ($request->input('per_page', 12));
        $products = $query->paginate($perPage)->appends($request->query());

        return response()->json($products);
    }

    // GET /api/products/{id}
    public function show($id)
    {
        $product = Product::with(['category', 'variants', 'images', 'surfaces', 'specs'])
            ->findOrFail($id);

        return response()->json($product);
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
        return response()->json($query);
    }
}
