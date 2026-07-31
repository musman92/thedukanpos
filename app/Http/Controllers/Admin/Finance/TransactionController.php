<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTransactionRequest;
use App\Http\Requests\Admin\UpdateTransactionRequest;
use App\Models\LedgerTransaction;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(protected TransactionService $transactions) {}

    public function index(Request $request): Response
    {
        $result = $this->transactions->paginate([
            'q' => $request->input('q'),
            'direction' => $request->input('direction'),
            'account_id' => $request->input('account_id'),
            'money_source_id' => $request->input('money_source_id'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'reference_type' => $request->input('reference_type'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'sort_direction' => $request->input('sort_direction'),
        ]);

        return Inertia::render('Admin/Transactions/Index', $result);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        try {
            $this->transactions->create($request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Transaction recorded.');
    }

    public function update(UpdateTransactionRequest $request, LedgerTransaction $transaction): RedirectResponse
    {
        try {
            $this->transactions->update($transaction, $request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Transaction updated.');
    }

    public function destroy(LedgerTransaction $transaction): RedirectResponse
    {
        try {
            $this->transactions->delete($transaction);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete transaction.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Transaction deleted.');
    }
}
