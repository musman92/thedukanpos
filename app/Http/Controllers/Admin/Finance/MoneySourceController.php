<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMoneySourceRequest;
use App\Http\Requests\Admin\UpdateMoneySourceRequest;
use App\Models\Branch;
use App\Models\MoneySource;
use App\Services\FinanceService;
use App\Services\MoneySourceService;
use App\Services\OwnerWithdrawalService;
use App\Support\BranchContext;
use App\Support\MoneySourceFundLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MoneySourceController extends Controller
{
    public function __construct(
        protected MoneySourceService $sources,
        protected FinanceService $finance,
        protected OwnerWithdrawalService $ownerWithdrawals,
    ) {}

    public function index(Request $request): Response
    {
        $result = $this->sources->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return Inertia::render('Admin/MoneySources/Index', [
            ...$result,
            'active_nav' => 'sources',
            'branch' => BranchContext::branch()?->only(['id', 'name']),
        ]);
    }

    public function store(StoreMoneySourceRequest $request): RedirectResponse
    {
        $this->sources->create($request->payload());

        return back()->with('status', 'Money source created.');
    }

    public function update(UpdateMoneySourceRequest $request, MoneySource $moneySource): RedirectResponse
    {
        $this->sources->update($moneySource, $request->payload());

        return back()->with('status', 'Money source updated.');
    }

    public function destroy(MoneySource $moneySource): RedirectResponse
    {
        try {
            $this->sources->delete($moneySource);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete money source.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Money source deleted.');
    }

    public function transferForm(): Response
    {
        $this->sources->seedDefaults();

        return Inertia::render('Admin/MoneySources/Transfer', [
            'active_nav' => 'transfer',
            'sources' => $this->sources->operationalOptions(),
            'branch' => BranchContext::ensure()->only(['id', 'name']),
        ]);
    }

    public function transfer(Request $request): RedirectResponse
    {
        $branch = BranchContext::ensure();
        $data = $request->validate([
            'from_money_source_id' => ['required', 'exists:money_sources,id'],
            'to_money_source_id' => ['required', 'exists:money_sources,id', 'different:from_money_source_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transfer_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $from = MoneySource::query()->findOrFail($data['from_money_source_id']);
        $to = MoneySource::query()->findOrFail($data['to_money_source_id']);

        if (! $from->isSelectableForPayment() || ! $to->isSelectableForPayment()) {
            return back()->withErrors(['from_money_source_id' => 'Invalid money source selection.']);
        }

        try {
            $this->finance->transferBetweenSources(
                (int) $data['from_money_source_id'],
                (int) $data['to_money_source_id'],
                (float) $data['amount'],
                $branch->id,
                $data['transfer_date'],
                $data['notes'] ?? null,
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Transfer recorded.');
    }

    public function ownerWithdrawalForm(): Response
    {
        $this->sources->seedDefaults();
        $owner = MoneySource::ownerWithdrawal();

        return Inertia::render('Admin/MoneySources/OwnerWithdrawal', [
            'active_nav' => 'owner-withdrawal',
            'sources' => $this->sources->operationalOptions(),
            'owner_bucket' => $owner ? [
                'id' => $owner->id,
                'name' => $owner->name,
                'balance' => round((float) $owner->balance, 2),
            ] : null,
            'branch' => BranchContext::ensure()->only(['id', 'name']),
        ]);
    }

    public function ownerWithdrawal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_money_source_id' => ['required', 'exists:money_sources,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->ownerWithdrawals->recordForCurrentBranch(
                (int) $data['from_money_source_id'],
                (float) $data['amount'],
                $data['date'],
                $data['notes'] ?? null,
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Owner withdrawal recorded.');
    }

    public function reports(Request $request): Response
    {
        $this->sources->seedDefaults();
        $ledger = MoneySourceFundLedger::build($request);

        return Inertia::render('Admin/MoneySources/Reports', [
            'active_nav' => 'reports',
            'filters' => $ledger['filters'],
            'rows' => $ledger['rows'],
            'summary' => $ledger['summary'],
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'sources' => MoneySourceFundLedger::sourceOptions(),
        ]);
    }
}
