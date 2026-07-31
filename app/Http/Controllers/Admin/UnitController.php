<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUnitRequest;
use App\Http\Requests\Admin\UpdateUnitRequest;
use App\Models\Unit;
use App\Services\UnitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function __construct(protected UnitService $units) {}

    public function index(Request $request): Response
    {
        $result = $this->units->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return Inertia::render('Admin/Units/Index', $result);
    }

    public function store(StoreUnitRequest $request): RedirectResponse
    {
        $this->units->create($request->payload());

        return back()->with('status', 'Unit created.');
    }

    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        $this->units->update($unit, $request->payload());

        return back()->with('status', 'Unit updated.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        try {
            $this->units->delete($unit);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete unit.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Unit deleted.');
    }
}
