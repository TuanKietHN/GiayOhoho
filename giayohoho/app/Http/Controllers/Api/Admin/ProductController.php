<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSpecsShoes;
use App\Models\Surface;
use App\Models\Tag;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q');
        $items = Product::with(['category', 'surfaces', 'tags', 'specs'])
            ->when($q, function ($b) use ($q) {
                $b->where(function ($s) use ($q) {
                    $s->where('name', 'like', "%{$q}%")
                      ->orWhere('brand', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(50);
        return response()->json($items);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'nullable|integer|exists:categories,id',
            'name' => 'required|string',
            'slug' => 'required|string|unique:products,slug',
            'brand' => 'nullable|string',
            'gender' => 'nullable|string|in:male,female,unisex',
            'base_price' => 'required|integer',
            'description' => 'nullable|string',
            'surfaces' => 'array',
            'surfaces.*' => 'string',
            'tags' => 'array',
            'tags.*' => 'string',
            'specs' => 'array',
        ]);
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
        return response()->json($product->load(['surfaces', 'tags', 'specs']), 201);
    }

    public function update(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $data = $request->validate([
            'category_id' => 'nullable|integer|exists:categories,id',
            'name' => 'sometimes|required|string',
            'slug' => 'sometimes|required|string|unique:products,slug,'.$product->id,
            'brand' => 'nullable|string',
            'gender' => 'nullable|string|in:male,female,unisex',
            'base_price' => 'nullable|integer',
            'description' => 'nullable|string',
            'surfaces' => 'array',
            'surfaces.*' => 'string',
            'tags' => 'array',
            'tags.*' => 'string',
            'specs' => 'array',
        ]);
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
        return response()->json($product->load(['surfaces', 'tags', 'specs']));
    }

    public function destroy(int $id)
    {
        Product::findOrFail($id)->delete();
        return response()->json(['message' => 'deleted']);
    }
}

