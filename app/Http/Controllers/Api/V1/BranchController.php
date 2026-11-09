<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBranchRequest;
use App\Http\Requests\Admin\UpdateBranchRequest;
use App\Models\Branch;
use App\Services\BranchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function __construct(protected BranchService $branches) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->branches->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);
        $page = $result['branches'];

        return response()->json([
            'data' => array_values($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = $this->branches->create($request->payload());
        $branch->loadCount(['users', 'stocks']);

        return response()->json([
            'data' => $this->branches->serialize($branch),
        ], 201);
    }

    public function show(Branch $branch): JsonResponse
    {
        $branch->loadCount(['users', 'stocks']);

        return response()->json([
            'data' => $this->branches->serialize($branch),
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $branch = $this->branches->update($branch, $request->payload());
        $branch->loadCount(['users', 'stocks']);

        return response()->json([
            'data' => $this->branches->serialize($branch),
        ]);
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $this->branches->delete($branch);

        return response()->json(['message' => 'Branch deleted.']);
    }
}
