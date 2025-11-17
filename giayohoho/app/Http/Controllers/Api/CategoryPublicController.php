<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryPublicController extends Controller
{
    public function index()
    {
        return response()->json(Category::with('children')->orderBy('name')->get());
    }
}