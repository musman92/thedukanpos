<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Services\HrService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function index(): Response
    {
        $branch = BranchContext::ensure();

        return Inertia::render('Admin/Hr/Payroll/Index', [
            'runs' => PayrollRun::query()
                ->with('branch:id,name')
                ->when($branch->id, fn ($q) => $q->where(function ($inner) use ($branch) {
                    $inner->where('branch_id', $branch->id)->orWhereNull('branch_id');
                }))
                ->latest('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (PayrollRun $run) => [
                    'id' => $run->id,
                    'number' => $run->number,
                    'period_start' => $run->period_start?->format('Y-m-d'),
                    'period_end' => $run->period_end?->format('Y-m-d'),
                    'employee_count' => $run->employee_count,
                    'net_total' => round((float) $run->net_total, 2),
                    'status' => $run->status,
                    'branch' => $run->branch
                        ? ['id' => $run->branch->id, 'name' => $run->branch->name]
                        : null,
                ]),
            'branch' => $branch->only(['id', 'name']),
        ]);
    }

    public function create(Request $request, HrService $hr): RedirectResponse
    {
        $branch = BranchContext::ensure();
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $run = $hr->generatePayrollRun(
            $data['period_start'],
            $data['period_end'],
            isset($data['branch_id']) ? (int) $data['branch_id'] : $branch->id,
        );

        return redirect()
            ->route('admin.hr.payroll.show', $run)
            ->with('status', 'Payroll run generated.');
    }

    public function show(PayrollRun $payroll): Response
    {
        $payroll->load(['items.user:id,name,username', 'branch:id,name', 'generator:id,name', 'finalizer:id,name']);

        return Inertia::render('Admin/Hr/Payroll/Show', [
            'run' => [
                'id' => $payroll->id,
                'number' => $payroll->number,
                'period_start' => $payroll->period_start?->format('Y-m-d'),
                'period_end' => $payroll->period_end?->format('Y-m-d'),
                'status' => $payroll->status,
                'employee_count' => $payroll->employee_count,
                'gross_total' => round((float) $payroll->gross_total, 2),
                'deduction_total' => round((float) $payroll->deduction_total, 2),
                'net_total' => round((float) $payroll->net_total, 2),
                'branch' => $payroll->branch
                    ? ['id' => $payroll->branch->id, 'name' => $payroll->branch->name]
                    : null,
                'items' => $payroll->items->map(fn ($item) => [
                    'id' => $item->id,
                    'user_id' => $item->user_id,
                    'pay_rate' => round((float) $item->pay_rate, 2),
                    'bonus_amount' => round((float) $item->bonus_amount, 2),
                    'deduction_amount' => round((float) $item->deduction_amount, 2),
                    'gross_pay' => round((float) $item->gross_pay, 2),
                    'net_pay' => round((float) $item->net_pay, 2),
                    'paid_amount' => round((float) ($item->paid_amount ?? 0), 2),
                    'status' => $item->status,
                    'user' => $item->user
                        ? [
                            'id' => $item->user->id,
                            'name' => $item->user->name ?: $item->user->username,
                        ]
                        : null,
                ]),
            ],
        ]);
    }

    public function finalize(PayrollRun $payroll, HrService $hr): RedirectResponse
    {
        try {
            $hr->finalizePayrollRun($payroll);
        } catch (\Throwable $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'Payroll finalized.');
    }
}
