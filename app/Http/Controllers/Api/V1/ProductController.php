<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Branch;
use App\Models\Product;
use App\Services\ProductService;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(protected ProductService $products) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $branchId = $this->resolveBranchId($request);

        $result = $this->products->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
            'branch_id' => $branchId,
        ]);

        return ProductResource::collection($result['products'])
            ->additional([
                'filters' => [
                    ...$result['filters'],
                    'branch_id' => $branchId,
                ],
            ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $payload = $request->payload();
        $branchId = $payload['branch_id'] ?? $this->resolveBranchId($request);
        $product = $this->products->create($payload, $branchId);

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Product $product): ProductResource
    {
        $branchId = $this->resolveBranchId($request);

        return new ProductResource($this->products->loadForBranch($product, $branchId));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $payload = $request->payload();
        $branchId = $payload['branch_id'] ?? $this->resolveBranchId($request);
        $product = $this->products->update($product, $payload, $branchId);

        return new ProductResource($product);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->products->delete($product);

        return response()->json(['message' => 'Product deleted.']);
    }

    protected function resolveBranchId(Request $request): int
    {
        if ($request->filled('branch_id')) {
            $branchId = (int) $request->input('branch_id');
            if (! Branch::query()->whereKey($branchId)->where('is_active', true)->exists()) {
                abort(422, 'Invalid or inactive branch_id.');
            }

            return $branchId;
        }

        return BranchContext::ensure()->id;
    }
}
