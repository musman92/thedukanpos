<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRackRequest;
use App\Http\Requests\Admin\UpdateRackRequest;
use App\Models\Rack;
use App\Models\Section;
use App\Services\RackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RackController extends Controller
{
    public function __construct(protected RackService $racks) {}

    public function index(Request $request): Response
    {
        $result = $this->racks->paginate([
            'q' => $request->input('q'),
            'section_id' => $request->input('section_id'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return Inertia::render('Admin/Racks/Index', [
            ...$result,
            'sections' => Section::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    public function store(StoreRackRequest $request): RedirectResponse
    {
        $this->racks->create($request->payload());

        return back()->with('status', 'Rack created.');
    }

    public function update(UpdateRackRequest $request, Rack $rack): RedirectResponse
    {
        $this->racks->update($rack, $request->payload());

        return back()->with('status', 'Rack updated.');
    }

    public function destroy(Rack $rack): RedirectResponse
    {
        try {
            $this->racks->delete($rack);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete rack.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Rack deleted.');
    }
}
