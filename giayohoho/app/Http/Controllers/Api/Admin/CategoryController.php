<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(Category::with('children')->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'parent_id' => 'nullable|integer|exists:categories,id',
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:categories,slug',
            'description' => 'nullable|string',
        ]);
        $cat = Category::create($data);
        return response()->json($cat, 201);
    }

    public function update(Request $request, int $id)
    {
        $cat = Category::findOrFail($id);
        $data = $request->validate([
            'parent_id' => 'nullable|integer|exists:categories,id',
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:categories,slug,'.$cat->id,
            'description' => 'nullable|string',
        ]);
        $cat->update($data);
        return response()->json($cat);
    }

    public function destroy(int $id)
    {
        Category::findOrFail($id)->delete();
        return response()->json(['message' => 'deleted']);
    }
}

