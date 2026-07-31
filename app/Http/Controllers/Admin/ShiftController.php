<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MoneySource;
use App\Models\Shift;
use App\Models\ShiftMoneySource;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function index(Request $request): Response
    {
        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($request->input('per_page'), $companyDefault);

        $shifts = Shift::query()
            ->with(['branch:id,name', 'opener:id,name', 'closer:id,name'])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (Shift $shift) {
                $diff = $shift->cashDifference();

                return [
                    'id' => $shift->id,
                    'branch' => $shift->branch?->name,
                    'shift_date' => optional($shift->shift_date)->toDateString()
                        ?? optional($shift->opened_at)->toDateString(),
                    'opened_by' => $shift->opener?->name,
                    'opened_at' => optional($shift->opened_at)?->format('Y-m-d H:i'),
                    'closed_by' => $shift->closer?->name,
                    'closed_at' => optional($shift->closed_at)?->format('Y-m-d H:i'),
                    'status' => $shift->isOpen() ? 'active' : 'closed',
                    'cash_difference' => $diff,
                    'opening_cash' => $shift->opening_cash,
                    'closing_cash' => $shift->closing_cash,
                    'expected_cash' => $shift->expected_cash,
                ];
            });

        return Inertia::render('Admin/Shifts/Index', [
            'shifts' => $shifts,
            'filters' => [
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
            ],
        ]);
    }

    public function create(): Response
    {
        $branch = BranchContext::ensure();

        return Inertia::render('Admin/Shifts/Create', [
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'moneySources' => MoneySource::query()
                ->forPayments()
                ->forBranch(BranchContext::ensure()->id)
                ->orderBy('id')
                ->get(['id', 'name', 'code', 'type']),
            'defaults' => [
                'branch_id' => $branch->id,
                'shift_date' => now()->toDateString(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'shift_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'money_sources' => ['required', 'array', 'min:1'],
            'money_sources.*.money_source_id' => ['required', 'exists:money_sources,id'],
            'money_sources.*.opening_balance' => ['required', 'numeric', 'min:0'],
        ]);

        if (Shift::query()->where('branch_id', $data['branch_id'])->open()->exists()) {
            return back()->withErrors(['branch_id' => 'A shift is already open for this branch.']);
        }

        $sources = MoneySource::query()
            ->whereIn('id', collect($data['money_sources'])->pluck('money_source_id'))
            ->get()
            ->keyBy('id');

        $openingCash = 0;
        foreach ($data['money_sources'] as $row) {
            $ms = $sources->get((int) $row['money_source_id']);
            if ($ms && $ms->isCash()) {
                $openingCash += (float) $row['opening_balance'];
            }
        }

        $shift = DB::transaction(function () use ($data, $openingCash) {
            $shift = Shift::query()->create([
                'branch_id' => $data['branch_id'],
                'shift_date' => $data['shift_date'],
                'opened_by' => Auth::id(),
                'opened_at' => now(),
                'opening_cash' => $openingCash,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['money_sources'] as $row) {
                ShiftMoneySource::query()->create([
                    'shift_id' => $shift->id,
                    'money_source_id' => $row['money_source_id'],
                    'opening_balance' => $row['opening_balance'],
                ]);
            }

            BranchContext::set((int) $data['branch_id']);

            return $shift;
        });

        return redirect()
            ->route('admin.shifts.show', $shift)
            ->with('status', 'Shift started.');
    }

    public function show(Shift $shift): Response
    {
        $shift->load([
            'branch:id,name',
            'opener:id,name',
            'closer:id,name',
            'moneySources.moneySource:id,name,code,type',
        ]);

        return Inertia::render('Admin/Shifts/Show', [
            'shift' => [
                'id' => $shift->id,
                'branch' => $shift->branch?->name,
                'shift_date' => optional($shift->shift_date)->toDateString(),
                'opened_by' => $shift->opener?->name,
                'opened_at' => optional($shift->opened_at)?->format('Y-m-d H:i'),
                'closed_by' => $shift->closer?->name,
                'closed_at' => optional($shift->closed_at)?->format('Y-m-d H:i'),
                'status' => $shift->isOpen() ? 'active' : 'closed',
                'opening_cash' => $shift->opening_cash,
                'closing_cash' => $shift->closing_cash,
                'expected_cash' => $shift->expected_cash,
                'cash_difference' => $shift->cashDifference(),
                'notes' => $shift->notes,
                'money_sources' => $shift->moneySources->map(fn (ShiftMoneySource $row) => [
                    'id' => $row->id,
                    'name' => $row->moneySource?->name,
                    'type' => $row->moneySource?->type,
                    'opening_balance' => $row->opening_balance,
                    'closing_balance' => $row->closing_balance,
                    'expected_balance' => $row->expected_balance,
                    'difference' => $row->difference,
                ]),
            ],
        ]);
    }

    public function close(Request $request, Shift $shift): RedirectResponse
    {
        if (! $shift->isOpen()) {
            return back()->withErrors(['shift' => 'Shift already closed.']);
        }

        $data = $request->validate([
            'closing_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $cashSales = $shift->sales()
            ->with('payments.moneySource')
            ->get()
            ->flatMap->payments
            ->filter(fn ($p) => $p->moneySource?->isCash())
            ->sum('amount');

        $expected = (float) $shift->opening_cash + (float) $cashSales;

        $shift->update([
            'closed_by' => Auth::id(),
            'closed_at' => now(),
            'closing_cash' => $data['closing_cash'],
            'expected_cash' => $expected,
            'notes' => trim(($shift->notes ? $shift->notes."\n" : '').($data['notes'] ?? '')),
        ]);

        $cashRow = $shift->moneySources()
            ->whereHas('moneySource', fn ($q) => $q->where('type', 'CASH'))
            ->first();

        if ($cashRow) {
            $cashRow->update([
                'closing_balance' => $data['closing_cash'],
                'expected_balance' => $expected,
                'difference' => (float) $data['closing_cash'] - $expected,
            ]);
        }

        return back()->with('status', 'Shift closed.');
    }
}
