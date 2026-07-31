<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Tax;
use App\Support\BranchContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    /**
     * @param  array{
     *   q?:string|null,
     *   status?:string|null,
     *   customer_id?:int|string|null,
     *   from?:string|null,
     *   to?:string|null,
     *   per_page?:int|string|null,
     *   sort?:string|null,
     *   direction?:string|null
     * }  $filters
     * @return array{
     *   quotations: LengthAwarePaginator,
     *   filters: array<string, mixed>,
     *   customers: Collection,
     *   branch: array{id:int, name:string}
     * }
     */
    public function paginate(array $filters = []): array
    {
        $branch = BranchContext::ensure();
        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        $customerId = $filters['customer_id'] !== null && $filters['customer_id'] !== ''
            ? (int) $filters['customer_id']
            : null;
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if (! in_array($status, Quotation::STATUSES, true)) {
            $status = '';
        }
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $quotations = Quotation::query()
            ->with(['customer:id,name', 'branch:id,name'])
            ->where('branch_id', $branch->id)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('number', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($from, fn ($query) => $query->whereDate('quote_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('quote_date', '<=', $to))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Quotation $quotation) => $this->serializeList($quotation));

        return [
            'quotations' => $quotations,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'customer_id' => $customerId,
                'from' => $from ? (string) $from : '',
                'to' => $to ? (string) $to : '',
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'customers' => $this->customerOptions(),
            'branch' => $branch->only(['id', 'name']),
        ];
    }

    /**
     * @return array{
     *   customers: Collection,
     *   variants: Collection,
     *   branch: array{id:int, name:string}
     * }
     */
    public function formOptions(): array
    {
        $branch = BranchContext::ensure();

        return [
            'customers' => $this->customerOptions(),
            'variants' => ProductVariant::query()
                ->with([
                    'product:id,name,tax_id',
                    'product.tax:id,name,rate,is_inclusive',
                    'purchaseUnit:id,name,code',
                    'saleUnit:id,name,code',
                ])
                ->where('is_active', true)
                ->whereHas('product', fn ($q) => $q->where('is_active', true))
                ->orderBy('short_code')
                ->get()
                ->map(fn (ProductVariant $v) => [
                    'id' => $v->id,
                    'label' => $v->displayName(),
                    'short_code' => $v->short_code,
                    'purchase_unit_id' => $v->purchase_unit_id,
                    'sale_unit_id' => $v->sale_unit_id,
                    'conversion_rate' => (float) $v->conversion_rate,
                    'cost_per_unit' => round((float) $v->cost_per_unit, 4),
                    'sale_price' => round((float) $v->sale_price, 4),
                    'purchase_unit' => $v->purchaseUnit
                        ? ['id' => $v->purchaseUnit->id, 'name' => $v->purchaseUnit->name, 'code' => $v->purchaseUnit->code]
                        : null,
                    'sale_unit' => $v->saleUnit
                        ? ['id' => $v->saleUnit->id, 'name' => $v->saleUnit->name, 'code' => $v->saleUnit->code]
                        : null,
                    'tax' => $v->product?->tax
                        ? [
                            'id' => $v->product->tax->id,
                            'name' => $v->product->tax->name,
                            'rate' => (float) $v->product->tax->rate,
                            'is_inclusive' => (bool) $v->product->tax->is_inclusive,
                        ]
                        : null,
                ])
                ->values(),
            'branch' => $branch->only(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Quotation $quotation): array
    {
        $quotation->load([
            'items.product:id,name',
            'items.variant:id,name,short_code',
            'items.unit:id,name,code',
            'customer:id,name,phone,balance',
            'branch:id,name',
            'creator:id,name',
        ]);

        return [
            'quotation' => $this->serializeDetail($quotation),
            'branch' => BranchContext::ensure()->only(['id', 'name']),
        ];
    }

    /**
     * @param  array{
     *   customer_id?:int|null,
     *   number?:string|null,
     *   quote_date:string,
     *   valid_until?:string|null,
     *   status?:string|null,
     *   discount_total?:float|int|string,
     *   notes?:string|null,
     *   items: list<array{
     *     variant_id:int,
     *     unit_id:int,
     *     quantity:float|int|string,
     *     unit_price:float|int|string,
     *     discount?:float|int|string
     *   }>
     * }  $data
     */
    public function create(array $data): Quotation
    {
        $branch = BranchContext::ensure();

        return DB::transaction(function () use ($data, $branch) {
            $items = $data['items'] ?? [];
            if ($items === []) {
                throw ValidationException::withMessages([
                    'items' => 'Add at least one line item.',
                ]);
            }

            $status = strtolower(trim((string) ($data['status'] ?? 'draft')));
            if (! in_array($status, Quotation::STATUSES, true)) {
                $status = 'draft';
            }

            $number = trim((string) ($data['number'] ?? ''));
            if ($number === '') {
                $number = $this->nextNumber();
            } elseif (Quotation::query()->where('number', $number)->exists()) {
                throw ValidationException::withMessages([
                    'number' => 'This quote number is already in use.',
                ]);
            }

            $discountTotal = round((float) ($data['discount_total'] ?? 0), 4);
            $totals = $this->calculateTotals($items, $discountTotal);

            $customerId = isset($data['customer_id']) && $data['customer_id'] !== '' && $data['customer_id'] !== null
                ? (int) $data['customer_id']
                : null;

            $quotation = Quotation::query()->create([
                'number' => $number,
                'branch_id' => $branch->id,
                'customer_id' => $customerId,
                'status' => $status,
                'quote_date' => $data['quote_date'],
                'valid_until' => ! empty($data['valid_until']) ? $data['valid_until'] : null,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'discount_total' => $discountTotal,
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->syncItems($quotation, $items);

            app(ActivityLogger::class)->log(
                'quotation.create',
                "Quotation {$quotation->number} total {$totals['total']}",
                $quotation,
            );

            return $quotation->fresh(['items.product', 'items.variant', 'customer', 'branch']);
        });
    }

    /**
     * @param  array{
     *   customer_id?:int|null,
     *   number?:string|null,
     *   quote_date:string,
     *   valid_until?:string|null,
     *   status?:string|null,
     *   discount_total?:float|int|string,
     *   notes?:string|null,
     *   items: list<array{
     *     variant_id:int,
     *     unit_id:int,
     *     quantity:float|int|string,
     *     unit_price:float|int|string,
     *     discount?:float|int|string
     *   }>
     * }  $data
     */
    public function update(Quotation $quotation, array $data): Quotation
    {
        return DB::transaction(function () use ($quotation, $data) {
            $quotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            $this->assertMutable($quotation);

            $items = $data['items'] ?? [];
            if ($items === []) {
                throw ValidationException::withMessages([
                    'items' => 'Add at least one line item.',
                ]);
            }

            $status = strtolower(trim((string) ($data['status'] ?? $quotation->status)));
            if (! in_array($status, Quotation::STATUSES, true)) {
                $status = $quotation->status;
            }

            $number = trim((string) ($data['number'] ?? ''));
            if ($number === '') {
                $number = $quotation->number;
            } elseif (
                Quotation::query()
                    ->where('number', $number)
                    ->where('id', '!=', $quotation->id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'number' => 'This quote number is already in use.',
                ]);
            }

            $discountTotal = round((float) ($data['discount_total'] ?? 0), 4);
            $totals = $this->calculateTotals($items, $discountTotal);

            $customerId = isset($data['customer_id']) && $data['customer_id'] !== '' && $data['customer_id'] !== null
                ? (int) $data['customer_id']
                : null;

            $quotation->update([
                'number' => $number,
                'customer_id' => $customerId,
                'status' => $status,
                'quote_date' => $data['quote_date'],
                'valid_until' => ! empty($data['valid_until']) ? $data['valid_until'] : null,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'discount_total' => $discountTotal,
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
            ]);

            $quotation->items()->delete();
            $this->syncItems($quotation, $items);

            app(ActivityLogger::class)->log(
                'quotation.update',
                "Quotation {$quotation->number} updated · total {$totals['total']}",
                $quotation,
            );

            return $quotation->fresh(['items.product', 'items.variant', 'customer', 'branch']);
        });
    }

    public function delete(Quotation $quotation): void
    {
        DB::transaction(function () use ($quotation) {
            $quotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            $this->assertMutable($quotation);

            $number = $quotation->number;
            $quotation->items()->delete();
            $quotation->delete();

            app(ActivityLogger::class)->log(
                'quotation.delete',
                "Quotation {$number} deleted",
                null,
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeForForm(Quotation $quotation): array
    {
        $quotation->loadMissing([
            'items.product:id,name',
            'items.variant:id,name,short_code',
            'items.unit:id,name,code',
            'items.tax:id,name,rate,is_inclusive',
        ]);

        return [
            'id' => $quotation->id,
            'customer_id' => $quotation->customer_id,
            'number' => $quotation->number,
            'quote_date' => $quotation->quote_date?->format('Y-m-d'),
            'valid_until' => $quotation->valid_until?->format('Y-m-d') ?? '',
            'status' => $quotation->status,
            'discount_total' => round((float) $quotation->discount_total, 2),
            'notes' => $quotation->notes ?? '',
            'items' => $quotation->items->map(function (QuotationItem $item) {
                $label = trim(($item->product?->name ?? '').(($item->variant?->name) ? ' – '.$item->variant->name : ''));

                return [
                    'variant_id' => (string) $item->variant_id,
                    'unit_id' => (string) $item->unit_id,
                    'quantity' => (string) round((float) $item->quantity, 4),
                    'unit_price' => (string) round((float) $item->unit_price, 4),
                    'discount' => (string) round((float) $item->discount, 4),
                    'display_name' => $label !== '' ? $label : ($item->variant?->name ?? 'Item'),
                    'short_code' => $item->variant?->short_code ?? '',
                    'sale_unit_label' => $item->unit?->code ?: ($item->unit?->name ?: '—'),
                    'tax' => $item->tax_id ? [
                        'id' => $item->tax_id,
                        'name' => $item->tax_name ?? $item->tax?->name,
                        'rate' => (float) $item->tax_rate,
                        'is_inclusive' => (bool) ($item->tax?->is_inclusive ?? false),
                    ] : null,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeList(Quotation $quotation): array
    {
        return [
            'id' => $quotation->id,
            'number' => $quotation->number,
            'quote_date' => $quotation->quote_date?->format('Y-m-d'),
            'valid_until' => $quotation->valid_until?->format('Y-m-d'),
            'status' => $quotation->status,
            'total' => round((float) $quotation->total, 2),
            'customer' => $quotation->customer
                ? ['id' => $quotation->customer->id, 'name' => $quotation->customer->name]
                : null,
            'can_edit' => ! $quotation->isConverted(),
            'can_delete' => ! $quotation->isConverted(),
        ];
    }

    protected function nextNumber(): string
    {
        $prefix = 'QT-'.now()->format('Ymd').'-';
        $last = Quotation::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    protected function assertMutable(Quotation $quotation): void
    {
        if ($quotation->isConverted()) {
            throw ValidationException::withMessages([
                'quotation' => 'Converted quotations cannot be edited or deleted.',
            ]);
        }
    }

    /**
     * @param  list<array{
     *   variant_id:int,
     *   unit_id:int,
     *   quantity:float|int|string,
     *   unit_price:float|int|string,
     *   discount?:float|int|string
     * }>  $items
     */
    protected function syncItems(Quotation $quotation, array $items): void
    {
        foreach ($items as $row) {
            $variant = ProductVariant::query()->with(['product.tax'])->findOrFail($row['variant_id']);
            $unitId = (int) $row['unit_id'];
            $qty = (float) $row['quantity'];
            $unitPrice = (float) $row['unit_price'];
            $lineDiscount = (float) ($row['discount'] ?? 0);
            $qtySale = $variant->toSaleQuantity($qty, $unitId);

            $line = $this->calculateLine($variant, $qty, $unitPrice, $lineDiscount);

            QuotationItem::query()->create([
                'quotation_id' => $quotation->id,
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'unit_id' => $unitId,
                'quantity' => $qty,
                'conversion_rate' => $variant->conversion_rate,
                'quantity_in_sale_unit' => $qtySale,
                'unit_price' => $unitPrice,
                'discount' => $lineDiscount,
                'tax_id' => $line['tax_id'],
                'tax_name' => $line['tax_name'],
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                'line_total' => $line['line_total'],
            ]);
        }
    }

    /**
     * @param  list<array{
     *   variant_id:int,
     *   unit_id:int,
     *   quantity:float|int|string,
     *   unit_price:float|int|string,
     *   discount?:float|int|string
     * }>  $items
     * @return array{subtotal: float, tax_total: float, total: float}
     */
    protected function calculateTotals(array $items, float $discountTotal): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($items as $row) {
            $variant = ProductVariant::query()->with(['product.tax'])->findOrFail($row['variant_id']);
            $line = $this->calculateLine(
                $variant,
                (float) $row['quantity'],
                (float) $row['unit_price'],
                (float) ($row['discount'] ?? 0),
            );
            $subtotal += $line['line_net'];
            $taxTotal += $line['tax_amount'];
        }

        $total = round(max(0, $subtotal + $taxTotal - $discountTotal), 4);

        return [
            'subtotal' => round($subtotal, 4),
            'tax_total' => round($taxTotal, 4),
            'total' => $total,
        ];
    }

    /**
     * @return array{
     *   line_net: float,
     *   tax_id: int|null,
     *   tax_name: string|null,
     *   tax_rate: float,
     *   tax_amount: float,
     *   line_total: float
     * }
     */
    protected function calculateLine(
        ProductVariant $variant,
        float $qty,
        float $unitPrice,
        float $lineDiscount,
    ): array {
        $product = $variant->product;
        $gross = ($qty * $unitPrice) - $lineDiscount;
        $tax = $product?->tax;
        $taxRate = $tax ? (float) $tax->rate : 0;
        $taxAmount = 0.0;
        $lineNet = $gross;

        if ($tax instanceof Tax && $taxRate > 0) {
            if ($tax->is_inclusive) {
                $lineNet = $gross / (1 + ($taxRate / 100));
                $taxAmount = $gross - $lineNet;
            } else {
                $taxAmount = $gross * ($taxRate / 100);
            }
        }

        return [
            'line_net' => round($lineNet, 4),
            'tax_id' => $tax?->id,
            'tax_name' => $tax?->name,
            'tax_rate' => $taxRate,
            'tax_amount' => round($taxAmount, 4),
            'line_total' => round($lineNet + $taxAmount, 4),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'quote_date', 'number', 'total', 'status', 'valid_until'];
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
     * @return Collection<int, array{id:int, name:string, phone:?string, balance:float}>
     */
    protected function customerOptions(): Collection
    {
        return Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'balance'])
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'balance' => round((float) $c->balance, 2),
            ])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeDetail(Quotation $quotation): array
    {
        return [
            ...$this->serializeList($quotation),
            'subtotal' => round((float) $quotation->subtotal, 2),
            'tax_total' => round((float) $quotation->tax_total, 2),
            'discount_total' => round((float) $quotation->discount_total, 2),
            'notes' => $quotation->notes,
            'branch' => $quotation->branch
                ? ['id' => $quotation->branch->id, 'name' => $quotation->branch->name]
                : null,
            'customer' => $quotation->customer
                ? [
                    'id' => $quotation->customer->id,
                    'name' => $quotation->customer->name,
                    'phone' => $quotation->customer->phone,
                    'balance' => round((float) $quotation->customer->balance, 2),
                ]
                : null,
            'items' => $quotation->items->map(fn (QuotationItem $item) => [
                'id' => $item->id,
                'variant_id' => $item->variant_id,
                'unit_id' => $item->unit_id,
                'product_name' => $item->product?->name,
                'variant_name' => $item->variant?->name,
                'variant_code' => $item->variant?->short_code,
                'unit_code' => $item->unit?->code,
                'quantity' => round((float) $item->quantity, 4),
                'quantity_in_sale_unit' => round((float) $item->quantity_in_sale_unit, 4),
                'unit_price' => round((float) $item->unit_price, 4),
                'discount' => round((float) $item->discount, 4),
                'tax_name' => $item->tax_name,
                'tax_rate' => round((float) $item->tax_rate, 4),
                'tax_amount' => round((float) $item->tax_amount, 2),
                'line_total' => round((float) $item->line_total, 2),
            ])->values(),
        ];
    }
}
