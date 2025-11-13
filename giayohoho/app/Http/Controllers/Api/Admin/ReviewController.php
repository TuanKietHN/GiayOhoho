<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;

class ReviewController extends Controller
{
    public function index()
    {
        return response()->json(Review::with(['user', 'product'])->orderByDesc('id')->paginate(100));
    }

    public function destroy(int $id)
    {
        Review::findOrFail($id)->delete();
        return response()->json(['message' => 'deleted']);
    }
}

