<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductSizeGuide;
use Illuminate\Http\Request;

class ProductSizeGuideController extends Controller
{
    public function index(Request $request)
    {
        $size = (int) $request->input('size', 20);
        $page = $request->has('page') ? ((int) $request->input('page')) + 1 : null;
        $q = $request->string('q')->toString();
        $guides = ProductSizeGuide::with('product')
            ->when($q, fn($query) => $query->where('title', 'like', "%{$q}%")->orWhere('brand', 'like', "%{$q}%"))
            ->orderByDesc('id')
            ->paginate($size, ['*'], 'page', $page);
        $items = $guides->getCollection()->map(fn(ProductSizeGuide $guide) => $this->payload($guide))->values();

        return response()->json([
            'content' => $items,
            'page' => max(0, $guides->currentPage() - 1),
            'size' => $guides->perPage(),
            'totalElements' => $guides->total(),
            'totalPages' => $guides->lastPage(),
            'last' => $guides->currentPage() >= $guides->lastPage(),
            'first' => $guides->currentPage() === 1,
        ]);
    }

    public function show(int $id)
    {
        return $this->ok($this->payload(ProductSizeGuide::with('product')->findOrFail($id)));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $guide = ProductSizeGuide::create($data);

        return $this->created($this->payload($guide->load('product')));
    }

    public function update(Request $request, int $id)
    {
        $guide = ProductSizeGuide::findOrFail($id);
        $guide->update($this->validated($request, true));

        return $this->ok($this->payload($guide->refresh()->load('product')));
    }

    public function destroy(int $id)
    {
        ProductSizeGuide::findOrFail($id)->delete();

        return $this->ok(null, 'deleted');
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $data = $request->validate([
            'productId' => ($partial ? 'nullable' : 'nullable').'|integer|exists:products,id',
            'brand' => 'nullable|string|max:255',
            'productType' => 'nullable|string|max:120',
            'title' => ($partial ? 'sometimes|' : 'required|').'string|max:180',
            'measurementUnit' => 'nullable|string|max:30',
            'measurementInstructions' => 'nullable|string',
            'fitNotes' => 'nullable|string',
            'sizeChart' => 'nullable|string',
            'active' => 'nullable|boolean',
        ]);

        $mapped = [];
        foreach ([
            'productId' => 'product_id',
            'brand' => 'brand',
            'productType' => 'product_type',
            'title' => 'title',
            'measurementUnit' => 'measurement_unit',
            'measurementInstructions' => 'measurement_instructions',
            'fitNotes' => 'fit_notes',
            'sizeChart' => 'size_chart',
        ] as $from => $to) {
            if (array_key_exists($from, $data)) {
                $mapped[$to] = $data[$from];
            }
        }
        if (array_key_exists('active', $data)) {
            $mapped['is_active'] = (bool) $data['active'];
        }
        if (! $partial) {
            $mapped['measurement_unit'] ??= 'EU';
            $mapped['is_active'] ??= true;
        }

        return $mapped;
    }

    private function payload(ProductSizeGuide $guide): array
    {
        return [
            'id' => $guide->id,
            'productId' => $guide->product_id,
            'productName' => $guide->product?->name,
            'productSlug' => $guide->product?->slug,
            'brand' => $guide->brand,
            'productType' => $guide->product_type,
            'title' => $guide->title,
            'measurementUnit' => $guide->measurement_unit,
            'measurementInstructions' => $guide->measurement_instructions,
            'fitNotes' => $guide->fit_notes,
            'sizeChart' => $guide->size_chart,
            'active' => (bool) $guide->is_active,
            'createdAt' => $guide->created_at?->toIso8601String(),
            'updatedAt' => $guide->updated_at?->toIso8601String(),
        ];
    }
}
