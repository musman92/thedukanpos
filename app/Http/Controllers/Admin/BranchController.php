<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBranchRequest;
use App\Http\Requests\Admin\UpdateBranchRequest;
use App\Models\Branch;
use App\Services\BranchService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function __construct(protected BranchService $branches) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Branches/Index', $this->branches->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]));
    }

    public function switch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        $branch = Branch::query()
            ->where('id', $data['branch_id'])
            ->where('is_active', true)
            ->firstOrFail();

        BranchContext::set($branch->id);

        return back()->with('status', 'Switched to '.$branch->name.'.');
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        try {
            $this->branches->create($request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Branch created.');
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        try {
            $this->branches->update($branch, $request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Branch updated.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        try {
            $this->branches->delete($branch);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete branch.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Branch deleted.');
    }
}
