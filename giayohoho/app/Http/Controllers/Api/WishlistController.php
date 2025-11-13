<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $items = Wishlist::with('product')->where('user_id', $request->user()->id)->get();
        return response()->json($items);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
        ]);
        $item = Wishlist::firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $data['product_id'],
        ]);
        return response()->json($item, 201);
    }

    public function destroy(Request $request, int $id)
    {
        $item = Wishlist::where('user_id', $request->user()->id)->findOrFail($id);
        $item->delete();
        return response()->json(['message' => 'deleted']);
    }
}

