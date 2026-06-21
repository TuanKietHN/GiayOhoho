<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    public function list(Request $request)
    {
        $size = min(100, max(1, (int) $request->input('size', 20)));
        $page = max(0, (int) $request->input('page', 0));
        $sortBy = in_array($request->input('sortBy'), ['id', 'sku', 'size', 'color', 'stock', 'created_at'], true)
            ? $request->input('sortBy')
            : 'id';
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $status = strtolower(trim((string) $request->input('status', 'active')));
        $stockStatus = strtolower(trim((string) $request->input('stockStatus', '')));
        $q = trim((string) $request->input('q', ''));

        $query = ProductVariant::query()
            ->with(['product.category', 'product.images'])
            ->when($status === 'deleted', fn ($builder) => $builder->onlyTrashed())
            ->when($status === 'all', fn ($builder) => $builder->withTrashed())
            ->whereHas('product', fn ($builder) => $builder
                ->when($request->filled('productId'), fn ($product) => $product->where('id', $request->integer('productId')))
                ->when($request->filled('categoryId'), fn ($product) => $product->where('category_id', $request->integer('categoryId')))
                ->when($request->filled('brand'), fn ($product) => $product->where('brand', 'like', '%'.trim((string) $request->input('brand')).'%')))
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($search) use ($q) {
                    $search->where('sku', 'like', "%{$q}%")
                        ->orWhere('size', 'like', "%{$q}%")
                        ->orWhere('color', 'like', "%{$q}%")
                        ->orWhereHas('product', fn ($product) => $product
                            ->where('name', 'like', "%{$q}%")
                            ->orWhere('slug', 'like', "%{$q}%"));
                });
            })
            ->when($stockStatus === 'out', fn ($builder) => $builder->where('stock', 0))
            ->when($stockStatus === 'low', fn ($builder) => $builder->where('stock', '>', 0)->where('stock', '<', 10))
            ->when($stockStatus === 'in', fn ($builder) => $builder->where('stock', '>=', 10))
            ->orderBy($sortBy, $direction)
            ->paginate($size, ['*'], 'page', $page + 1);

        return $this->ok([
            'content' => $query->getCollection()->map(fn (ProductVariant $variant) => $this->row($variant))->values(),
            'page' => $page,
            'size' => $query->perPage(),
            'totalElements' => $query->total(),
            'totalPages' => $query->lastPage(),
            'first' => $page === 0,
            'last' => $query->currentPage() >= $query->lastPage(),
        ]);
    }

    public function index(int $productId)
    {
        return response()->json(ProductVariant::where('product_id', $productId)->orderBy('id')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'size' => 'required|string',
            'color' => 'required|string',
            'sku' => 'nullable|string',
            'stock' => 'integer',
            'extra_price' => 'integer',
        ]);
        $variant = ProductVariant::create($data);
        return response()->json($variant, 201);
    }

    public function update(Request $request, int $id)
    {
        $variant = ProductVariant::findOrFail($id);
        $data = $request->validate([
            'size' => 'nullable|string',
            'color' => 'nullable|string',
            'sku' => 'nullable|string',
            'stock' => 'integer',
            'extra_price' => 'integer',
        ]);
        $variant->update($data);
        return response()->json($variant);
    }

    public function destroy(int $id)
    {
        ProductVariant::findOrFail($id)->delete();
        return response()->json(['message' => 'deleted']);
    }

    public function adjustStock(Request $request, int $id)
    {
        $data = $request->validate([
            'delta' => 'required|integer',
        ]);
        $variant = ProductVariant::findOrFail($id);
        $variant->stock = max(0, (int) $variant->stock + (int) $data['delta']);
        $variant->save();
        return response()->json($variant);
    }

    public function lowStock(Request $request)
    {
        $threshold = (int) $request->input('threshold', 5);
        $items = ProductVariant::with('product')->where('stock', '<', $threshold)->orderBy('stock')->get();
        return response()->json($items);
    }

    private function row(ProductVariant $variant): array
    {
        $product = $variant->product;
        $activeImages = $product?->images ?? collect();
        $variantImages = $activeImages->where('product_variant_id', $variant->id);
        $image = $variantImages
            ->sortBy([['is_primary', 'desc'], ['sort_order', 'asc'], ['id', 'asc']])
            ->first()
            ?? $activeImages->sortBy([['is_primary', 'desc'], ['sort_order', 'asc'], ['id', 'asc']])->first();

        return [
            'id' => $variant->id,
            'productId' => $product?->id,
            'productName' => $product?->name,
            'productSlug' => $product?->slug,
            'categoryName' => $product?->category?->name,
            'brand' => $product?->brand,
            'size' => $variant->size,
            'color' => $variant->color,
            'sku' => $variant->sku,
            'stock' => (int) $variant->stock,
            'finalPrice' => (int) ($product?->base_price ?? 0) + (int) $variant->extra_price,
            'basePrice' => (int) ($product?->base_price ?? 0),
            'imageUrl' => $image?->image_url,
            'imageAltText' => $image?->alt_text,
            'imageCount' => $variantImages->count(),
            'hasVariantImage' => $variantImages->isNotEmpty(),
            'deletedAt' => $variant->deleted_at?->toIso8601String(),
        ];
    }
}
