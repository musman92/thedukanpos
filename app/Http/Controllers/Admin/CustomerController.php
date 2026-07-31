<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Services\CustomerService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(protected CustomerService $customers) {}

    public function index(Request $request): Response
    {
        $result = $this->customers->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return Inertia::render('Admin/Customers/Index', [
            ...$result,
            'recentPayments' => CustomerPayment::query()
                ->with(['customer:id,name', 'moneySource:id,name'])
                ->where('branch_id', BranchContext::ensure()->id)
                ->latest('id')
                ->limit(15)
                ->get(),
            'dueCustomers' => Customer::query()
                ->where('is_active', true)
                ->where('balance', '>', 0)
                ->orderBy('name')
                ->get(['id', 'name', 'balance']),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->customers->create($request->payload());

        return back()->with('status', 'Customer created.');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->customers->update($customer, $request->payload());

        return back()->with('status', 'Customer updated.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        try {
            $this->customers->delete($customer);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete customer.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Customer deleted.');
    }

    public function receivePayment(Request $request): RedirectResponse
    {
        $branch = BranchContext::ensure();
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'money_source_id' => ['required', 'exists:money_sources,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->customers->receivePayment([
                ...$data,
                'branch_id' => $branch->id,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Payment recorded.');
    }
}
