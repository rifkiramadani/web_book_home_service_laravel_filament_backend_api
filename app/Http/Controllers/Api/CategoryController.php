<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CategoryApiResource;

class CategoryController extends Controller
{
    public function index(Request $request) {
        $categories = Category::query();

        if($request->has('limit')) {
            $categories->limit($request->input('limit'));
        }

        return CategoryApiResource::collection($categories->get());
    }

    public function shoW(Category $category) {
        $category->load(['homeServices']);

        return new CategoryApiResource($category);
    }
}
