<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVariationRequest;
use App\Http\Requests\Admin\UpdateVariationRequest;
use App\Models\Variation;
use App\Services\VariationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VariationController extends Controller
{
    public function __construct(protected VariationService $variations) {}

    public function index(Request $request): Response
    {
        $result = $this->variations->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return Inertia::render('Admin/Variations/Index', $result);
    }

    public function store(StoreVariationRequest $request): RedirectResponse
    {
        $this->variations->create($request->payload());

        return back()->with('status', 'Variation created.');
    }

    public function update(UpdateVariationRequest $request, Variation $variation): RedirectResponse
    {
        $this->variations->update($variation, $request->payload());

        return back()->with('status', 'Variation updated.');
    }

    public function destroy(Variation $variation): RedirectResponse
    {
        try {
            $this->variations->delete($variation);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete variation.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Variation deleted.');
    }
}
