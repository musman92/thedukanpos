<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function __construct(protected PlanService $plans) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Platform/Plans/Index', [
            ...$this->plans->page([
                'q' => $request->input('q'),
                'per_page' => $request->input('per_page'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
            ]),
            'form_open' => $request->boolean('open'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = $this->plans->create($this->validated($request));

        return redirect()
            ->route('platform.plans.index')
            ->with('status', "Plan {$plan->name} created.");
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan = $this->plans->update($plan, $this->validated($request, $plan));

        return redirect()
            ->route('platform.plans.index')
            ->with('status', "Plan {$plan->name} updated.");
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        try {
            $name = $plan->name;
            $this->plans->delete($plan);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()
            ->route('platform.plans.index')
            ->with('status', "Plan {$name} deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('plans', 'code')->ignore($plan?->id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'product_limit' => ['nullable', 'integer', 'min:0'],
            'user_limit' => ['nullable', 'integer', 'min:0'],
            'branch_limit' => ['nullable', 'integer', 'min:0'],
            'ads_enabled' => ['boolean'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'quarterly_price' => ['required', 'numeric', 'min:0'],
            'yearly_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $data['code'] = strtolower($data['code']);
        $data['ads_enabled'] = $request->boolean('ads_enabled');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
