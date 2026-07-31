<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    /**
     * @param  array{q?:string|null, per_page?:int|string|null, sort?:string|null, direction?:string|null}  $filters
     * @return array{customers: LengthAwarePaginator, filters: array{q: string, per_page: int, company_page_limit: int, sort: string, direction: string}}
     */
    public function paginate(array $filters = []): array
    {
        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $customers = Customer::query()
            ->withCount('sales')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString();

        return [
            'customers' => $customers,
            'filters' => [
                'q' => $q,
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'name', 'code', 'balance'];
        $sort = strtolower(trim((string) ($sort ?? 'id')));
        if (! in_array($sort, $allowed, true)) {
            $sort = 'id';
        }

        $direction = strtolower(trim((string) ($direction ?? 'desc')));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = $sort === 'id' ? 'desc' : 'asc';
        }

        return [$sort, $direction];
    }

    /**
     * @param  array{name:string, code?:string|null, phone?:string|null, email?:string|null, address?:string|null, opening_balance?:float|int|string|null, is_active?:bool}  $data
     */
    public function create(array $data): Customer
    {
        $name = trim((string) $data['name']);
        $code = Customer::resolveCode($data['code'] ?? null);
        $this->assertNameAvailable($name);
        $this->assertCodeAvailable($code);

        $opening = max(0, (float) ($data['opening_balance'] ?? 0));

        return Customer::query()->create([
            'name' => $name,
            'code' => $code,
            'phone' => $this->nullableString($data['phone'] ?? null),
            'email' => $this->nullableString($data['email'] ?? null),
            'address' => $this->nullableString($data['address'] ?? null),
            'balance' => $opening,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);
    }

    /**
     * @param  array{name:string, code?:string|null, phone?:string|null, email?:string|null, address?:string|null, is_active?:bool}  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        if ($customer->is_system) {
            $customer->update([
                'phone' => $this->nullableString($data['phone'] ?? null),
                'email' => $this->nullableString($data['email'] ?? null),
                'address' => $this->nullableString($data['address'] ?? null),
                'is_active' => true,
            ]);

            return $customer->refresh();
        }

        $name = trim((string) $data['name']);
        $input = trim((string) ($data['code'] ?? ''));
        $code = $input !== ''
            ? strtoupper($input)
            : ($customer->code ?: Customer::resolveCode(null));

        $this->assertNameAvailable($name, $customer->id);
        $this->assertCodeAvailable($code, $customer->id);

        $customer->update([
            'name' => $name,
            'code' => $code,
            'phone' => $this->nullableString($data['phone'] ?? null),
            'email' => $this->nullableString($data['email'] ?? null),
            'address' => $this->nullableString($data['address'] ?? null),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $customer->is_active,
        ]);

        return $customer->refresh();
    }

    public function delete(Customer $customer): void
    {
        if ($customer->is_system) {
            throw ValidationException::withMessages([
                'customer' => 'System customers cannot be deleted.',
            ]);
        }

        $salesCount = $customer->sales()->count();
        if ($salesCount > 0) {
            throw ValidationException::withMessages([
                'customer' => "Cannot delete this customer because they have {$salesCount} sale(s).",
            ]);
        }

        if ((float) $customer->balance > 0.0001) {
            throw ValidationException::withMessages([
                'customer' => 'Cannot delete this customer while they still have a balance due.',
            ]);
        }

        $customer->payments()->delete();
        $customer->delete();
    }

    /**
     * Increase what the customer owes (credit sale unpaid amount).
     */
    public function charge(Customer $customer, float $amount, ?string $notes = null): Customer
    {
        if ($amount <= 0) {
            return $customer;
        }

        $customer->balance = (float) $customer->balance + $amount;
        $customer->save();

        return $customer;
    }

    /**
     * Decrease what the customer owes (payment received or credit return).
     */
    public function credit(Customer $customer, float $amount): Customer
    {
        if ($amount <= 0) {
            return $customer;
        }

        $customer->balance = max(0, (float) $customer->balance - $amount);
        $customer->save();

        return $customer;
    }

    /**
     * @param  array{
     *   customer_id:int,
     *   branch_id?:int|null,
     *   money_source_id:int,
     *   shift_id?:int|null,
     *   amount:float|int|string,
     *   payment_date?:string|null,
     *   notes?:string|null
     * }  $data
     */
    public function receivePayment(array $data): CustomerPayment
    {
        return app(CustomerPaymentService::class)->create([
            'customer_id' => (int) $data['customer_id'],
            'money_source_id' => (int) $data['money_source_id'],
            'payment_date' => (string) ($data['payment_date'] ?? now()->toDateString()),
            'notes' => $data['notes'] ?? null,
            'total_amount' => (float) $data['amount'],
        ]);
    }

    protected function assertNameAvailable(string $name, ?int $ignoreId = null): void
    {
        $exists = Customer::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'This customer name is already taken.',
            ]);
        }
    }

    protected function assertCodeAvailable(string $code, ?int $ignoreId = null): void
    {
        $exists = Customer::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'This customer code is already taken.',
            ]);
        }
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
