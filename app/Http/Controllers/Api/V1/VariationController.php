<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVariationRequest;
use App\Http\Requests\Admin\UpdateVariationRequest;
use App\Http\Resources\Api\V1\VariationResource;
use App\Models\Variation;
use App\Services\VariationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VariationController extends Controller
{
    public function __construct(protected VariationService $variations) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $result = $this->variations->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return VariationResource::collection($result['variations'])
            ->additional(['filters' => $result['filters']]);
    }

    public function store(StoreVariationRequest $request): JsonResponse
    {
        $variation = $this->variations->create($request->payload());

        return (new VariationResource($variation))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Variation $variation): VariationResource
    {
        $variation->load(['options' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->loadCount('options');

        return new VariationResource($variation);
    }

    public function update(UpdateVariationRequest $request, Variation $variation): VariationResource
    {
        $variation = $this->variations->update($variation, $request->payload());

        return new VariationResource($variation);
    }

    public function destroy(Variation $variation): JsonResponse
    {
        $this->variations->delete($variation);

        return response()->json(['message' => 'Variation deleted.']);
    }
}
