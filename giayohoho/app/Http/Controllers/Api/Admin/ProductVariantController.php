<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
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
}

