<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSectionRequest;
use App\Http\Requests\Admin\UpdateSectionRequest;
use App\Models\Section;
use App\Services\SectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SectionController extends Controller
{
    public function __construct(protected SectionService $sections) {}

    public function index(Request $request): Response
    {
        $result = $this->sections->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return Inertia::render('Admin/Sections/Index', $result);
    }

    public function store(StoreSectionRequest $request): RedirectResponse
    {
        $this->sections->create($request->payload());

        return back()->with('status', 'Section created.');
    }

    public function update(UpdateSectionRequest $request, Section $section): RedirectResponse
    {
        $this->sections->update($section, $request->payload());

        return back()->with('status', 'Section updated.');
    }

    public function destroy(Section $section): RedirectResponse
    {
        try {
            $this->sections->delete($section);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete section.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Section deleted.');
    }
}
