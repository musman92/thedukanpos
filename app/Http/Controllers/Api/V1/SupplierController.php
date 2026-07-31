<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Requests\Admin\UpdateSupplierRequest;
use App\Http\Resources\Api\V1\SupplierResource;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends Controller
{
    public function __construct(protected SupplierService $suppliers) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $result = $this->suppliers->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return SupplierResource::collection($result['suppliers'])
            ->additional(['filters' => $result['filters']]);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = $this->suppliers->create($request->payload());
        $supplier->loadCount('purchases');

        return (new SupplierResource($supplier))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Supplier $supplier): SupplierResource
    {
        $supplier->loadCount('purchases');

        return new SupplierResource($supplier);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): SupplierResource
    {
        $supplier = $this->suppliers->update($supplier, $request->payload());
        $supplier->loadCount('purchases');

        return new SupplierResource($supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->suppliers->delete($supplier);

        return response()->json(['message' => 'Supplier deleted.']);
    }
}
