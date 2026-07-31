<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Http\Resources\Api\V1\BrandResource;
use App\Models\Brand;
use App\Services\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    public function __construct(protected BrandService $brands) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $result = $this->brands->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return BrandResource::collection($result['brands'])
            ->additional(['filters' => $result['filters']]);
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $brand = $this->brands->create($request->payload());
        $brand->loadCount('products');

        return (new BrandResource($brand))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Brand $brand): BrandResource
    {
        $brand->loadCount('products');

        return new BrandResource($brand);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): BrandResource
    {
        $brand = $this->brands->update($brand, $request->payload());
        $brand->loadCount('products');

        return new BrandResource($brand);
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $this->brands->delete($brand);

        return response()->json(['message' => 'Brand deleted.']);
    }
}
