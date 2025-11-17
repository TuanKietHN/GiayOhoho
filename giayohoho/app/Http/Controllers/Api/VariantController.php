<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;

class VariantController extends Controller
{
    public function show(int $id)
    {
        $v = ProductVariant::with('product')->findOrFail($id);
        return response()->json($v);
    }
}