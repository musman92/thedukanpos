<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUnitRequest;
use App\Http\Requests\Admin\UpdateUnitRequest;
use App\Http\Resources\Api\V1\UnitResource;
use App\Models\Unit;
use App\Services\UnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitController extends Controller
{
    public function __construct(protected UnitService $units) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $result = $this->units->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return UnitResource::collection($result['units'])
            ->additional(['filters' => $result['filters']]);
    }

    public function store(StoreUnitRequest $request): JsonResponse
    {
        $unit = $this->units->create($request->payload());
        $unit->setAttribute('usage_count', 0);

        return (new UnitResource($unit))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Unit $unit): UnitResource
    {
        $unit->setAttribute('usage_count', $this->units->usageCount($unit));

        return new UnitResource($unit);
    }

    public function update(UpdateUnitRequest $request, Unit $unit): UnitResource
    {
        $unit = $this->units->update($unit, $request->payload());
        $unit->setAttribute('usage_count', $this->units->usageCount($unit));

        return new UnitResource($unit);
    }

    public function destroy(Unit $unit): JsonResponse
    {
        $this->units->delete($unit);

        return response()->json(['message' => 'Unit deleted.']);
    }
}
