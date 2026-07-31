<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\MoneySource;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Shift;
use App\Support\BranchContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public const PAYMENT_STATUSES = ['paid', 'partial', 'pending'];

    public function __construct(
        protected InventoryService $inventory,
        protected CustomerService $customers,
    ) {}

    /**
     * @param  array{
     *   q?:string|null,
     *   customer_id?:int|string|null,
     *   payment_status?:string|null,
     *   from?:string|null,
     *   to?:string|null,
     *   per_page?:int|string|null,
     *   sort?:string|null,
     *   direction?:string|null
     * }  $filters
     * @return array{
     *   sales: LengthAwarePaginator,
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
        $paymentStatus = strtolower(trim((string) ($filters['payment_status'] ?? '')));
        if (! in_array($paymentStatus, self::PAYMENT_STATUSES, true)) {
            $paymentStatus = '';
        }
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $sales = Sale::query()
            ->with(['customer:id,name', 'cashier:id,name,username'])
            ->where('branch_id', $branch->id)
            ->where('status', '!=', Sale::STATUS_PARKED)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('number', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->when($paymentStatus !== '', fn ($query) => $this->applyPaymentStatusFilter($query, $paymentStatus))
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Sale $sale) => $this->serializeList($sale));

        return [
            'sales' => $sales,
            'filters' => [
                'q' => $q,
                'customer_id' => $customerId,
                'payment_status' => $paymentStatus,
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
     *   sale: array<string, mixed>,
     *   branch: array{id:int, name:string}
     * }
     */
    public function show(Sale $sale): array
    {
        $sale->load([
            'items.product:id,name',
            'items.variant:id,name,short_code',
            'items.unit:id,name,code',
            'payments.moneySource:id,name',
            'customer:id,name,balance',
            'cashier:id,name,username',
            'branch:id,name',
            'returns:id,number,return_date,total,refunded_total',
        ]);

        return [
            'sale' => $this->serializeDetail($sale),
            'branch' => BranchContext::ensure()->only(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeList(Sale $sale): array
    {
        $total = (float) $sale->total;
        $paidTotal = (float) $sale->paid_total;

        return [
            'id' => $sale->id,
            'number' => $sale->number,
            'created_at' => format_company_datetime($sale->created_at),
            'created_at_date' => format_company_date($sale->created_at),
            'total' => round($total, 2),
            'paid_total' => round($paidTotal, 2),
            'balance_due' => round($sale->balanceDue(), 2),
            'payment_status' => $this->paymentStatusFor($total, $paidTotal),
            'customer' => $sale->customer
                ? ['id' => $sale->customer->id, 'name' => $sale->customer->name]
                : null,
            'cashier' => $sale->cashier
                ? [
                    'id' => $sale->cashier->id,
                    'name' => $sale->cashier->name ?: $sale->cashier->username,
                ]
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeDetail(Sale $sale): array
    {
        return [
            ...$this->serializeList($sale),
            'subtotal' => round((float) $sale->subtotal, 2),
            'tax_total' => round((float) $sale->tax_total, 2),
            'discount_total' => round((float) $sale->discount_total, 2),
            'notes' => $sale->notes,
            'status' => $sale->status,
            'branch' => $sale->branch
                ? ['id' => $sale->branch->id, 'name' => $sale->branch->name]
                : null,
            'items' => $sale->items->map(fn (SaleItem $item) => [
                'id' => $item->id,
                'variant_id' => $item->variant_id,
                'unit_id' => $item->unit_id,
                'product_name' => $item->product?->name,
                'variant_name' => $item->variant?->name,
                'variant_code' => $item->variant?->short_code,
                'unit_code' => $item->unit?->code,
                'quantity' => round((float) $item->quantity, 4),
                'unit_price' => round((float) $item->unit_price, 4),
                'discount' => round((float) $item->discount, 2),
                'tax_name' => $item->tax_name,
                'tax_rate' => round((float) $item->tax_rate, 2),
                'tax_amount' => round((float) $item->tax_amount, 2),
                'line_total' => round((float) $item->line_total, 2),
            ])->values(),
            'payments' => $sale->payments->map(fn (SalePayment $payment) => [
                'id' => $payment->id,
                'amount' => round((float) $payment->amount, 2),
                'money_source' => $payment->moneySource
                    ? ['id' => $payment->moneySource->id, 'name' => $payment->moneySource->name]
                    : null,
            ])->values(),
            'returns' => $sale->returns->map(fn ($return) => [
                'id' => $return->id,
                'number' => $return->number,
                'return_date' => format_company_date($return->return_date),
                'total' => round((float) $return->total, 2),
                'refunded_total' => round((float) $return->refunded_total, 2),
            ])->values(),
        ];
    }

    /**
     * @param  array{
     *   branch_id:int,
     *   shift_id?:int|null,
     *   customer_id?:int|null,
     *   discount_total?:float|int|string,
     *   notes?:string|null,
     *   items: list<array{
     *     variant_id:int,
     *     unit_id?:int,
     *     quantity:float|int|string,
     *     unit_price?:float|int|string,
     *     discount?:float|int|string
     *   }>,
     *   payments?: list<array{money_source_id:int, amount:float|int|string}>
     * }  $data
     */
    public function checkout(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $this->assertOpenShift($data['shift_id'] ?? null);

            $customerId = $data['customer_id'] ?? null;

            $sale = Sale::query()->create([
                'number' => $this->nextNumber(),
                'branch_id' => $data['branch_id'],
                'shift_id' => $data['shift_id'] ?? null,
                'customer_id' => $customerId,
                'cashier_id' => Auth::id(),
                'status' => Sale::STATUS_COMPLETED,
                'notes' => $data['notes'] ?? null,
            ]);

            $totals = $this->writeItems(
                sale: $sale,
                items: $data['items'],
                branchId: (int) $data['branch_id'],
                discountTotal: (float) ($data['discount_total'] ?? 0),
                deductStock: true,
            );

            $paid = $this->recordPayments($sale, $data['payments'] ?? []);
            $this->applyCreditIfNeeded(
                sale: $sale,
                customerId: $customerId,
                total: $totals['total'],
                paid: $paid,
                allowCredit: ($data['allow_credit'] ?? true) !== false,
            );

            $sale->update([
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'discount_total' => $totals['discount_total'],
                'total' => $totals['total'],
                'paid_total' => $paid,
            ]);

            app(ActivityLogger::class)->log(
                'sale.checkout',
                "Sale {$sale->number} total {$totals['total']}",
                $sale,
                ['total' => $totals['total'], 'paid' => $paid, 'due' => max(0, $totals['total'] - $paid)],
            );

            return $sale->load(['items.product', 'items.variant', 'payments.moneySource', 'customer']);
        });
    }

    /**
     * Save cart for later checkout — no stock movement, no payments.
     *
     * @param  array{
     *   branch_id:int,
     *   shift_id?:int|null,
     *   customer_id?:int|null,
     *   discount_total?:float|int|string,
     *   notes?:string|null,
     *   items: list<array{
     *     variant_id:int,
     *     unit_id?:int,
     *     quantity:float|int|string,
     *     unit_price?:float|int|string,
     *     discount?:float|int|string
     *   }>
     * }  $data
     */
    public function park(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $this->assertOpenShift($data['shift_id'] ?? null);

            $sale = Sale::query()->create([
                'number' => $this->nextNumber(),
                'branch_id' => $data['branch_id'],
                'shift_id' => $data['shift_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'cashier_id' => Auth::id(),
                'status' => Sale::STATUS_PARKED,
                'notes' => $data['notes'] ?? null,
                'paid_total' => 0,
            ]);

            $totals = $this->writeItems(
                sale: $sale,
                items: $data['items'],
                branchId: (int) $data['branch_id'],
                discountTotal: (float) ($data['discount_total'] ?? 0),
                deductStock: false,
            );

            $sale->update([
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'discount_total' => $totals['discount_total'],
                'total' => $totals['total'],
                'paid_total' => 0,
            ]);

            app(ActivityLogger::class)->log(
                'sale.park',
                "Parked bill {$sale->number}",
                $sale,
                ['total' => $totals['total'], 'lines' => count($data['items'])],
            );

            return $sale->load(['items.product', 'items.variant', 'items.unit', 'customer']);
        });
    }

    /**
     * @param  array{
     *   customer_id?:int|null,
     *   discount_total?:float|int|string,
     *   notes?:string|null,
     *   items: list<array{
     *     variant_id:int,
     *     unit_id?:int,
     *     quantity:float|int|string,
     *     unit_price?:float|int|string,
     *     discount?:float|int|string
     *   }>
     * }  $data
     */
    public function updateParked(Sale $sale, array $data): Sale
    {
        if (! $sale->isParked()) {
            throw new \RuntimeException('Only parked bills can be updated.');
        }

        return DB::transaction(function () use ($sale, $data) {
            $sale->items()->delete();

            $sale->update([
                'customer_id' => $data['customer_id'] ?? null,
                'cashier_id' => Auth::id(),
                'notes' => array_key_exists('notes', $data) ? ($data['notes'] ?? null) : $sale->notes,
            ]);

            $totals = $this->writeItems(
                sale: $sale,
                items: $data['items'],
                branchId: (int) $sale->branch_id,
                discountTotal: (float) ($data['discount_total'] ?? 0),
                deductStock: false,
            );

            $sale->update([
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'discount_total' => $totals['discount_total'],
                'total' => $totals['total'],
                'paid_total' => 0,
            ]);

            return $sale->fresh()->load(['items.product', 'items.variant', 'items.unit', 'customer']);
        });
    }

    /**
     * Finalize a parked bill: refresh lines, deduct stock, take payment.
     *
     * @param  array{
     *   customer_id?:int|null,
     *   discount_total?:float|int|string,
     *   notes?:string|null,
     *   items: list<array{
     *     variant_id:int,
     *     unit_id?:int,
     *     quantity:float|int|string,
     *     unit_price?:float|int|string,
     *     discount?:float|int|string
     *   }>,
     *   payments?: list<array{money_source_id:int, amount:float|int|string}>,
     *   allow_credit?:bool
     * }  $data
     */
    public function completeParked(Sale $sale, array $data): Sale
    {
        if (! $sale->isParked()) {
            throw new \RuntimeException('Only parked bills can be checked out.');
        }

        return DB::transaction(function () use ($sale, $data) {
            $this->assertOpenShift($sale->shift_id);

            $sale->items()->delete();

            $customerId = $data['customer_id'] ?? null;

            $sale->update([
                'customer_id' => $customerId,
                'cashier_id' => Auth::id(),
                'notes' => array_key_exists('notes', $data) ? ($data['notes'] ?? null) : $sale->notes,
                'status' => Sale::STATUS_COMPLETED,
            ]);

            $totals = $this->writeItems(
                sale: $sale,
                items: $data['items'],
                branchId: (int) $sale->branch_id,
                discountTotal: (float) ($data['discount_total'] ?? 0),
                deductStock: true,
            );

            $paid = $this->recordPayments($sale, $data['payments'] ?? []);
            $this->applyCreditIfNeeded(
                sale: $sale,
                customerId: $customerId,
                total: $totals['total'],
                paid: $paid,
                allowCredit: ($data['allow_credit'] ?? true) !== false,
            );

            $sale->update([
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'discount_total' => $totals['discount_total'],
                'total' => $totals['total'],
                'paid_total' => $paid,
            ]);

            app(ActivityLogger::class)->log(
                'sale.checkout',
                "Sale {$sale->number} total {$totals['total']} (from parked)",
                $sale,
                ['total' => $totals['total'], 'paid' => $paid, 'due' => max(0, $totals['total'] - $paid)],
            );

            return $sale->fresh()->load(['items.product', 'items.variant', 'payments.moneySource', 'customer']);
        });
    }

    public function discardParked(Sale $sale): void
    {
        if (! $sale->isParked()) {
            throw new \RuntimeException('Only parked bills can be discarded.');
        }

        DB::transaction(function () use ($sale) {
            $number = $sale->number;
            $sale->items()->delete();
            $sale->delete();

            app(ActivityLogger::class)->log(
                'sale.discard_parked',
                "Discarded parked bill {$number}",
                null,
                ['number' => $number],
            );
        });
    }

    /**
     * Today's sales for the branch (company timezone), including voided.
     *
     * @return list<array<string, mixed>>
     */
    public function listToday(int $branchId): array
    {
        $tz = (string) (company_settings()['timezone'] ?? config('app.timezone', 'UTC'));
        $start = now($tz)->startOfDay()->utc();
        $end = now($tz)->endOfDay()->utc();

        return Sale::query()
            ->with(['customer:id,name', 'cashier:id,name,username'])
            ->withCount('items')
            ->where('branch_id', $branchId)
            ->where('status', '!=', Sale::STATUS_PARKED)
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function (Sale $sale) {
                return [
                    ...$this->serializeList($sale),
                    'item_count' => (int) $sale->items_count,
                    'status' => $sale->status,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function posSaleDetail(Sale $sale): array
    {
        $sale->loadMissing([
            'customer:id,name',
            'cashier:id,name,username',
            'branch:id,name',
            'items.product:id,name',
            'items.variant:id,name,short_code',
            'items.unit:id,code',
            'payments.moneySource:id,name',
            'returns:id,number,return_date,total,refunded_total',
        ]);

        return $this->serializeDetail($sale);
    }

    /**
     * Void a completed sale: restore stock and reverse unpaid customer credit.
     */
    public function voidSale(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale) {
            $locked = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($locked->isParked()) {
                throw new \RuntimeException('Parked bills cannot be cancelled here. Discard them from Saved instead.');
            }

            if ($locked->status === Sale::STATUS_VOID) {
                throw new \RuntimeException('This sale is already cancelled.');
            }

            if ($locked->status !== Sale::STATUS_COMPLETED) {
                throw new \RuntimeException('Only completed sales can be cancelled.');
            }

            $locked->load(['items.variant.product', 'customer']);

            foreach ($locked->items as $item) {
                $variant = $item->variant;
                if (! $variant) {
                    continue;
                }
                $product = $variant->product;
                if (! $product?->track_stock) {
                    continue;
                }

                $qty = (float) $item->quantity_in_sale_unit;
                if ($qty <= 0) {
                    continue;
                }

                $unitCost = (float) ($item->cost_per_unit ?? $variant->cost_per_unit ?? 0);
                $this->inventory->receive(
                    branchId: (int) $locked->branch_id,
                    variant: $variant,
                    qtySaleUnits: $qty,
                    lineCostTotal: $qty * $unitCost,
                    reference: $item,
                    notes: "Void sale {$locked->number}",
                    type: 'sale_void',
                );
            }

            $due = round((float) $locked->total - (float) $locked->paid_total, 4);
            if ($due > 0.01 && $locked->customer_id) {
                $customer = Customer::query()->lockForUpdate()->find($locked->customer_id);
                if ($customer) {
                    $this->customers->credit($customer, $due);
                }
            }

            $locked->update(['status' => Sale::STATUS_VOID]);

            app(ActivityLogger::class)->log(
                'sale.void',
                "Voided sale {$locked->number}",
                $locked,
                ['total' => (float) $locked->total, 'paid' => (float) $locked->paid_total],
            );

            return $locked->fresh(['customer', 'cashier', 'items']);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listParked(int $branchId): array
    {
        return Sale::query()
            ->with(['customer:id,name', 'items.product:id,name', 'items.variant:id,name,short_code', 'items.unit:id,code'])
            ->where('branch_id', $branchId)
            ->where('status', Sale::STATUS_PARKED)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Sale $sale) => $this->serializeParked($sale))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeParked(Sale $sale): array
    {
        $sale->loadMissing([
            'customer:id,name',
            'items.product:id,name',
            'items.variant:id,name,short_code',
            'items.unit:id,code',
        ]);

        return [
            'id' => $sale->id,
            'number' => $sale->number,
            'customer_id' => $sale->customer_id,
            'customer' => $sale->customer
                ? ['id' => $sale->customer->id, 'name' => $sale->customer->name]
                : null,
            'discount_total' => round((float) $sale->discount_total, 2),
            'subtotal' => round((float) $sale->subtotal, 2),
            'tax_total' => round((float) $sale->tax_total, 2),
            'total' => round((float) $sale->total, 2),
            'notes' => $sale->notes,
            'updated_at' => format_company_datetime($sale->updated_at),
            'item_count' => $sale->items->count(),
            'items' => $sale->items->map(function (SaleItem $item) {
                $name = $item->variant
                    ? trim(($item->product?->name ?? '').($item->variant->name ? ' — '.$item->variant->name : ''))
                    : ($item->product?->name ?? 'Item');

                return [
                    'variant_id' => $item->variant_id,
                    'product_id' => $item->product_id,
                    'name' => $name !== '' ? $name : 'Item',
                    'unit_id' => $item->unit_id,
                    'unit_code' => $item->unit?->code,
                    'quantity' => round((float) $item->quantity, 4),
                    'unit_price' => round((float) $item->unit_price, 4),
                    'discount' => round((float) $item->discount, 2),
                    'tax' => $item->tax_id
                        ? [
                            'id' => $item->tax_id,
                            'name' => $item->tax_name,
                            'rate' => round((float) $item->tax_rate, 2),
                        ]
                        : null,
                    'location' => null,
                    'stock' => null,
                ];
            })->values()->all(),
        ];
    }

    protected function assertOpenShift(mixed $shiftId): void
    {
        if (! $shiftId) {
            return;
        }

        $shift = Shift::query()->findOrFail($shiftId);
        if (! $shift->isOpen()) {
            throw new \RuntimeException('Shift is closed.');
        }
    }

    /**
     * @param  list<array{
     *   variant_id:int,
     *   unit_id?:int,
     *   quantity:float|int|string,
     *   unit_price?:float|int|string,
     *   discount?:float|int|string
     * }>  $items
     * @return array{subtotal:float, tax_total:float, discount_total:float, total:float}
     */
    protected function writeItems(
        Sale $sale,
        array $items,
        int $branchId,
        float $discountTotal,
        bool $deductStock,
    ): array {
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($items as $row) {
            $variant = ProductVariant::query()->with(['product.tax', 'purchaseUnit', 'saleUnit'])->findOrFail($row['variant_id']);
            $product = $variant->product;
            $unitId = (int) ($row['unit_id'] ?? $variant->sale_unit_id);
            $qty = (float) $row['quantity'];
            $qtySale = $variant->toSaleQuantity($qty, $unitId);
            $unitPrice = isset($row['unit_price'])
                ? (float) $row['unit_price']
                : (float) $variant->sale_price;
            $lineDiscount = (float) ($row['discount'] ?? 0);

            if ($unitId === $variant->purchase_unit_id && $variant->hasDualUnits() && ! isset($row['unit_price'])) {
                $unitPrice = (float) $variant->sale_price * (float) $variant->conversion_rate;
            }

            $gross = ($qty * $unitPrice) - $lineDiscount;
            $tax = $product?->tax;
            $taxRate = $tax ? (float) $tax->rate : 0;
            $taxAmount = 0.0;
            $lineNet = $gross;

            if ($tax && $taxRate > 0) {
                if ($tax->is_inclusive) {
                    $lineNet = $gross / (1 + ($taxRate / 100));
                    $taxAmount = $gross - $lineNet;
                } else {
                    $taxAmount = $gross * ($taxRate / 100);
                    $lineNet = $gross;
                }
            }

            $lineTotal = $lineNet + $taxAmount;

            $item = SaleItem::query()->create([
                'sale_id' => $sale->id,
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'unit_id' => $unitId,
                'quantity' => $qty,
                'conversion_rate' => $variant->conversion_rate,
                'quantity_in_sale_unit' => $qtySale,
                'unit_price' => $unitPrice,
                'discount' => $lineDiscount,
                'tax_id' => $tax?->id,
                'tax_name' => $tax?->name,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
                'cost_per_unit' => $variant->cost_per_unit,
            ]);

            $subtotal += $lineNet;
            $taxTotal += $taxAmount;

            if ($deductStock && $product?->track_stock) {
                $this->inventory->deduct(
                    branchId: $branchId,
                    variant: $variant,
                    qtySaleUnits: $qtySale,
                    reference: $item,
                    notes: "Sale {$sale->number}",
                );
            }
        }

        $total = round($subtotal + $taxTotal - $discountTotal, 4);

        return [
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'discount_total' => $discountTotal,
            'total' => $total,
        ];
    }

    /**
     * @param  list<array{money_source_id:int, amount:float|int|string}>  $payments
     */
    protected function recordPayments(Sale $sale, array $payments): float
    {
        $paid = 0.0;

        foreach ($payments as $payment) {
            MoneySource::query()->findOrFail($payment['money_source_id']);
            $amount = (float) $payment['amount'];
            if ($amount <= 0) {
                continue;
            }
            SalePayment::query()->create([
                'sale_id' => $sale->id,
                'money_source_id' => $payment['money_source_id'],
                'amount' => $amount,
            ]);
            $paid += $amount;
        }

        return round($paid, 4);
    }

    protected function applyCreditIfNeeded(
        Sale $sale,
        mixed $customerId,
        float $total,
        float $paid,
        bool $allowCredit,
    ): void {
        if ($paid > $total + 0.01) {
            throw new \RuntimeException('Paid amount exceeds sale total.');
        }

        $due = round($total - $paid, 4);

        if ($due <= 0.01) {
            return;
        }

        if (! $allowCredit) {
            throw new \RuntimeException('Credit sales are disabled in settings. Collect full payment.');
        }

        if (! $customerId) {
            throw new \RuntimeException('Select a customer for credit / unpaid amount.');
        }

        $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);

        if ($customer->isWalkIn()) {
            throw new \RuntimeException('Select a customer (not walk-in) for credit / unpaid amount.');
        }

        $this->customers->charge($customer, $due, "Sale {$sale->number}");
    }

    protected function nextNumber(): string
    {
        $prefix = 'SL-'.now()->format('Ymd').'-';
        $last = Sale::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('number');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    protected function paymentStatusFor(float $total, float $paidTotal): string
    {
        if ($paidTotal + 0.01 >= $total) {
            return 'paid';
        }
        if ($paidTotal > 0.01) {
            return 'partial';
        }

        return 'pending';
    }

    protected function applyPaymentStatusFilter(Builder $query, string $paymentStatus): void
    {
        match ($paymentStatus) {
            'paid' => $query->whereRaw('paid_total >= total - 0.01'),
            'partial' => $query
                ->where('paid_total', '>', 0.01)
                ->whereRaw('paid_total < total - 0.01'),
            'pending' => $query->where('paid_total', '<=', 0.01),
            default => null,
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'number', 'created_at', 'total', 'paid_total'];
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
     * @return Collection<int, array{id:int, name:string}>
     */
    protected function customerOptions(): Collection
    {
        return Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
            ])
            ->values();
    }
}
