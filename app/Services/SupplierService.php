<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    /**
     * @param  array{q?:string|null, per_page?:int|string|null, sort?:string|null, direction?:string|null}  $filters
     * @return array{suppliers: LengthAwarePaginator, filters: array{q: string, per_page: int, company_page_limit: int, sort: string, direction: string}}
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

        $suppliers = Supplier::query()
            ->withCount('purchases')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('contact_person', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString();

        return [
            'suppliers' => $suppliers,
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
     * @param  array{name:string, code?:string|null, contact_person?:string|null, phone?:string|null, email?:string|null, address?:string|null, notes?:string|null, opening_balance?:float|int|string|null, is_active?:bool}  $data
     */
    public function create(array $data): Supplier
    {
        $name = trim((string) $data['name']);
        $code = Supplier::resolveCode($data['code'] ?? null);
        $this->assertNameAvailable($name);
        $this->assertCodeAvailable($code);

        $opening = max(0, (float) ($data['opening_balance'] ?? 0));

        return Supplier::query()->create([
            'name' => $name,
            'code' => $code,
            'contact_person' => $this->nullableString($data['contact_person'] ?? null),
            'phone' => $this->nullableString($data['phone'] ?? null),
            'email' => $this->nullableString($data['email'] ?? null),
            'address' => $this->nullableString($data['address'] ?? null),
            'notes' => $this->nullableString($data['notes'] ?? null),
            'balance' => $opening,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);
    }

    /**
     * @param  array{name:string, code?:string|null, contact_person?:string|null, phone?:string|null, email?:string|null, address?:string|null, notes?:string|null, is_active?:bool}  $data
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        $name = trim((string) $data['name']);
        $input = trim((string) ($data['code'] ?? ''));
        $code = $input !== ''
            ? strtoupper($input)
            : ($supplier->code ?: Supplier::resolveCode(null));

        $this->assertNameAvailable($name, $supplier->id);
        $this->assertCodeAvailable($code, $supplier->id);

        $supplier->update([
            'name' => $name,
            'code' => $code,
            'contact_person' => $this->nullableString($data['contact_person'] ?? null),
            'phone' => $this->nullableString($data['phone'] ?? null),
            'email' => $this->nullableString($data['email'] ?? null),
            'address' => $this->nullableString($data['address'] ?? null),
            'notes' => $this->nullableString($data['notes'] ?? null),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $supplier->is_active,
        ]);

        return $supplier->refresh();
    }

    public function delete(Supplier $supplier): void
    {
        $purchaseCount = $supplier->purchases()->count();
        if ($purchaseCount > 0) {
            throw ValidationException::withMessages([
                'supplier' => "Cannot delete this supplier because they have {$purchaseCount} purchase(s).",
            ]);
        }

        if ((float) $supplier->balance > 0.0001) {
            throw ValidationException::withMessages([
                'supplier' => 'Cannot delete this supplier while they still have a balance due.',
            ]);
        }

        $supplier->payments()->delete();
        $supplier->delete();
    }

    protected function assertNameAvailable(string $name, ?int $ignoreId = null): void
    {
        $exists = Supplier::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'This supplier name is already taken.',
            ]);
        }
    }

    protected function assertCodeAvailable(string $code, ?int $ignoreId = null): void
    {
        $exists = Supplier::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'This supplier code is already taken.',
            ]);
        }
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
