<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSectionRequest;
use App\Http\Requests\Admin\UpdateSectionRequest;
use App\Http\Resources\Api\V1\SectionResource;
use App\Models\Section;
use App\Services\SectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SectionController extends Controller
{
    public function __construct(protected SectionService $sections) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $result = $this->sections->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return SectionResource::collection($result['sections'])
            ->additional(['filters' => $result['filters']]);
    }

    public function store(StoreSectionRequest $request): JsonResponse
    {
        $section = $this->sections->create($request->payload());

        return (new SectionResource($section))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Section $section): SectionResource
    {
        $section->load(['racks' => fn ($q) => $q->orderBy('name')->orderBy('id')])
            ->loadCount('racks');

        return new SectionResource($section);
    }

    public function update(UpdateSectionRequest $request, Section $section): SectionResource
    {
        $section = $this->sections->update($section, $request->payload());

        return new SectionResource($section);
    }

    public function destroy(Section $section): JsonResponse
    {
        $this->sections->delete($section);

        return response()->json(['message' => 'Section deleted.']);
    }
}
