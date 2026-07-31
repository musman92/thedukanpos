<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRackRequest;
use App\Http\Requests\Admin\UpdateRackRequest;
use App\Http\Resources\Api\V1\RackResource;
use App\Models\Rack;
use App\Services\RackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RackController extends Controller
{
    public function __construct(protected RackService $racks) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $result = $this->racks->paginate([
            'q' => $request->input('q'),
            'section_id' => $request->input('section_id'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return RackResource::collection($result['racks'])
            ->additional(['filters' => $result['filters']]);
    }

    public function store(StoreRackRequest $request): JsonResponse
    {
        $rack = $this->racks->create($request->payload());
        $rack->load('section:id,name,code')->loadCount('locations');

        return (new RackResource($rack))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Rack $rack): RackResource
    {
        $rack->load('section:id,name,code')->loadCount('locations');

        return new RackResource($rack);
    }

    public function update(UpdateRackRequest $request, Rack $rack): RackResource
    {
        $rack = $this->racks->update($rack, $request->payload());
        $rack->load('section:id,name,code')->loadCount('locations');

        return new RackResource($rack);
    }

    public function destroy(Rack $rack): JsonResponse
    {
        $this->racks->delete($rack);

        return response()->json(['message' => 'Rack deleted.']);
    }
}
