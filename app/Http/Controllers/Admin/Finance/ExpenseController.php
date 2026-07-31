<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExpenseRequest;
use App\Http\Requests\Admin\UpdateExpenseRequest;
use App\Models\LedgerTransaction;
use App\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(protected ExpenseService $expenses) {}

    public function index(Request $request): Response
    {
        $result = $this->expenses->paginate([
            'q' => $request->input('q'),
            'account_id' => $request->input('account_id'),
            'money_source_id' => $request->input('money_source_id'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return Inertia::render('Admin/Expenses/Index', $result);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        try {
            $this->expenses->create($request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Expense recorded.');
    }

    public function update(UpdateExpenseRequest $request, LedgerTransaction $expense): RedirectResponse
    {
        try {
            $this->expenses->assertIsExpense($expense);
            $this->expenses->update($expense, $request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Expense updated.');
    }

    public function destroy(LedgerTransaction $expense): RedirectResponse
    {
        try {
            $this->expenses->delete($expense);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete expense.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Expense deleted.');
    }
}
