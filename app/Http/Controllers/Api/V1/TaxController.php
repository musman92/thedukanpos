<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTaxRequest;
use App\Http\Requests\Admin\UpdateTaxRequest;
use App\Http\Resources\Api\V1\TaxResource;
use App\Models\Tax;
use App\Services\TaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaxController extends Controller
{
    public function __construct(protected TaxService $taxes) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $result = $this->taxes->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return TaxResource::collection($result['taxes'])
            ->additional(['filters' => $result['filters']]);
    }

    public function store(StoreTaxRequest $request): JsonResponse
    {
        $tax = $this->taxes->create($request->payload());
        $tax->setAttribute('usage_count', 0);

        return (new TaxResource($tax))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Tax $tax): TaxResource
    {
        $tax->setAttribute('usage_count', $this->taxes->usageCount($tax));

        return new TaxResource($tax);
    }

    public function update(UpdateTaxRequest $request, Tax $tax): TaxResource
    {
        $tax = $this->taxes->update($tax, $request->payload());
        $tax->setAttribute('usage_count', $this->taxes->usageCount($tax));

        return new TaxResource($tax);
    }

    public function destroy(Tax $tax): JsonResponse
    {
        $this->taxes->delete($tax);

        return response()->json(['message' => 'Tax deleted.']);
    }
}
