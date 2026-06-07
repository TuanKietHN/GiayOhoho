<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Surface;
use App\Models\Tag;

class CategoryController extends Controller
{
    public function listCategories()
    {
        return response()->json(Category::with('children')->whereNull('deleted_at')->orderBy('name')->get());
    }

    public function getCategoryBySlug(string $slug)
    {
        return response()->json(Category::with('children')->where('slug', $slug)->firstOrFail());
    }

    public function listSurfaces()
    {
        return response()->json(Surface::orderBy('name')->get());
    }

    public function listTags()
    {
        return response()->json(Tag::orderBy('name')->get());
    }
}
