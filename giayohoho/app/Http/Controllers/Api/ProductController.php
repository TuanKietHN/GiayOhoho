<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // GET /api/products
    public function index(Request $request)
    {
        $products = Product::query()
            ->with(['category', 'variants', 'images', 'surfaces', 'specs'])
            ->paginate(12);

        return response()->json($products);
    }

    // GET /api/products/{id}
    public function show($id)
    {
        $product = Product::with(['category', 'variants', 'images', 'surfaces', 'specs'])
            ->findOrFail($id);

        return response()->json($product);
    }
}
