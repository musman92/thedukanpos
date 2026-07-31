<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(protected CategoryService $categories) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $result = $this->categories->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return CategoryResource::collection($result['categories'])
            ->additional(['filters' => $result['filters']]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categories->create($request->payload());
        $category->load(['parent:id,name,code', 'defaultTax:id,name,code,rate'])
            ->loadCount(['products', 'children']);

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        $category->load(['parent:id,name,code', 'defaultTax:id,name,code,rate'])
            ->loadCount(['products', 'children']);

        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category = $this->categories->update($category, $request->payload());
        $category->load(['parent:id,name,code', 'defaultTax:id,name,code,rate'])
            ->loadCount(['products', 'children']);

        return new CategoryResource($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->categories->delete($category);

        return response()->json(['message' => 'Category deleted.']);
    }
}
