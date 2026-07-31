<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Support\BranchContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleReturnService
{
    public function __construct(protected ReturnService $returns) {}

    /**
     * @param  array{
     *   q?:string|null,
     *   customer_id?:int|string|null,
     *   from?:string|null,
     *   to?:string|null,
     *   per_page?:int|string|null,
     *   sort?:string|null,
     *   direction?:string|null
     * }  $filters
     * @return array{
     *   returns: LengthAwarePaginator,
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
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $returns = SaleReturn::query()
            ->with(['customer:id,name', 'sale:id,number', 'branch:id,name'])
            ->where('branch_id', $branch->id)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('number', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('sale', fn ($s) => $s->where('number', 'like', "%{$q}%"));
                });
            })
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->when($from, fn ($query) => $query->whereDate('return_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('return_date', '<=', $to))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn ($query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (SaleReturn $doc) => $this->serializeList($doc));

        return [
            'returns' => $returns,
            'filters' => [
                'q' => $q,
                'customer_id' => $customerId,
                'from' => $from ? (string) $from : '',
                'to' => $to ? (string) $to : '',
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'customers' => Customer::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Customer $c) => ['id' => $c->id, 'name' => $c->name])
                ->values(),
            'branch' => $branch->only(['id', 'name']),
        ];
    }

    /**
     * @return array{
     *   sales: Collection,
     *   selected_sale: array<string, mixed>|null,
     *   branch: array{id:int, name:string}
     * }
     */
    public function formOptions(?int $saleId = null, ?SaleReturn $editingReturn = null): array
    {
        $branch = BranchContext::ensure();

        $extraReturned = [];
        if ($editingReturn) {
            $editingReturn->loadMissing('items');
            foreach ($editingReturn->items as $returnItem) {
                $sid = (int) $returnItem->sale_item_id;
                $extraReturned[$sid] = ($extraReturned[$sid] ?? 0) + (float) $returnItem->quantity;
            }
            $saleId = $saleId ?: (int) $editingReturn->sale_id;
        }

        $selected = null;
        if ($saleId) {
            $sale = Sale::query()
                ->with([
                    'customer:id,name',
                    'items.product:id,name',
                    'items.variant:id,name,short_code',
                    'items.unit:id,name,code',
                ])
                ->where('branch_id', $branch->id)
                ->where('status', 'completed')
                ->find($saleId);

            if ($sale) {
                $returnedQtys = $this->returnedQuantitiesForSale((int) $sale->id, $editingReturn);
                $selected = [
                    'id' => $sale->id,
                    'number' => $sale->number,
                    'sale_date' => $sale->created_at?->format('Y-m-d'),
                    'customer_id' => $sale->customer_id,
                    'customer_name' => $sale->customer?->name,
                    'total' => round((float) $sale->total, 2),
                    'items' => $sale->items
                        ->map(function (SaleItem $item) use ($returnedQtys, $extraReturned) {
                            $extra = $extraReturned[$item->id] ?? 0.0;
                            $returned = (float) ($returnedQtys[$item->id] ?? 0);
                            $returnable = round((float) $item->quantity - $returned, 4);

                            return [
                                'id' => $item->id,
                                'product_name' => $item->product?->name,
                                'variant_name' => $item->variant?->name,
                                'unit_code' => $item->unit?->code,
                                'quantity' => round((float) $item->quantity, 4),
                                'returnable_quantity' => $returnable,
                                'unit_price' => round((float) $item->unit_price, 4),
                            ];
                        })
                        ->filter(fn (array $row) => $row['returnable_quantity'] > 0.0001)
                        ->values(),
                ];
            }
        }

        $sales = Sale::query()
            ->with(['customer:id,name', 'items'])
            ->where('branch_id', $branch->id)
            ->where('status', 'completed')
            ->latest('id')
            ->limit(100)
            ->get()
            ->filter(function (Sale $sale) use ($saleId, $extraReturned, $editingReturn) {
                if ($saleId && (int) $sale->id === (int) $saleId) {
                    return true;
                }

                $returnedQtys = $this->returnedQuantitiesForSale((int) $sale->id, $editingReturn);

                return $sale->items->contains(function (SaleItem $item) use ($returnedQtys, $extraReturned) {
                    $extra = $extraReturned[$item->id] ?? 0.0;
                    $returnable = (float) $item->quantity - (float) ($returnedQtys[$item->id] ?? 0);

                    return $returnable > 0.0001;
                });
            })
            ->map(fn (Sale $s) => [
                'id' => $s->id,
                'number' => $s->number,
                'sale_date' => $s->created_at?->format('Y-m-d'),
                'customer_id' => $s->customer_id,
                'customer_name' => $s->customer?->name,
                'total' => round((float) $s->total, 2),
            ])
            ->values();

        return [
            'sales' => $sales,
            'selected_sale' => $selected,
            'branch' => $branch->only(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeList(SaleReturn $doc): array
    {
        return [
            'id' => $doc->id,
            'number' => $doc->number,
            'return_date' => $doc->return_date?->format('Y-m-d'),
            'total' => round((float) $doc->total, 2),
            'customer' => $doc->customer
                ? ['id' => $doc->customer->id, 'name' => $doc->customer->name]
                : null,
            'sale' => $doc->sale
                ? ['id' => $doc->sale->id, 'number' => $doc->sale->number]
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeForForm(SaleReturn $doc): array
    {
        $doc->loadMissing([
            'items.product:id,name',
            'items.variant:id,name,short_code',
            'items.unit:id,name,code',
            'sale:id,number,customer_id',
            'customer:id,name',
        ]);

        $qtyBySaleItem = [];
        foreach ($doc->items as $item) {
            $sid = (int) $item->sale_item_id;
            $qtyBySaleItem[$sid] = ($qtyBySaleItem[$sid] ?? 0) + (float) $item->quantity;
        }

        return [
            'id' => $doc->id,
            'number' => $doc->number,
            'sale_id' => $doc->sale_id,
            'return_date' => $doc->return_date?->format('Y-m-d'),
            'notes' => $doc->notes ?? '',
            'items' => collect($qtyBySaleItem)->map(fn ($qty, $saleItemId) => [
                'sale_item_id' => (int) $saleItemId,
                'quantity' => (string) round((float) $qty, 4),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(SaleReturn $doc): array
    {
        $doc->load([
            'items.product:id,name',
            'items.variant:id,name,short_code',
            'items.unit:id,name,code',
            'customer:id,name',
            'sale:id,number',
            'branch:id,name',
            'creator:id,name',
        ]);

        return [
            'return' => $this->serializeDetail($doc),
            'branch' => BranchContext::ensure()->only(['id', 'name']),
        ];
    }

    /**
     * @param  array{
     *   sale_id:int,
     *   return_date:string,
     *   notes?:string|null,
     *   items: list<array{sale_item_id:int, quantity:float|int|string}>
     * }  $data
     */
    public function create(array $data): SaleReturn
    {
        $branch = BranchContext::ensure();

        return DB::transaction(function () use ($data, $branch) {
            $sale = Sale::query()
                ->with('items')
                ->where('branch_id', $branch->id)
                ->where('status', 'completed')
                ->lockForUpdate()
                ->findOrFail($data['sale_id']);

            $lines = collect($data['items'] ?? [])
                ->filter(fn ($row) => (float) ($row['quantity'] ?? 0) > 0)
                ->values();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Enter a return quantity for at least one line.',
                ]);
            }

            $returnedQtys = $this->returnedQuantitiesForSale((int) $sale->id);

            foreach ($lines as $row) {
                /** @var SaleItem|null $saleItem */
                $saleItem = $sale->items->firstWhere('id', (int) $row['sale_item_id']);
                if (! $saleItem) {
                    throw ValidationException::withMessages([
                        'items' => 'A return line does not belong to this sale.',
                    ]);
                }

                $qty = (float) $row['quantity'];
                $returnable = round((float) $saleItem->quantity - (float) ($returnedQtys[$saleItem->id] ?? 0), 4);

                if ($qty <= 0.0001) {
                    throw ValidationException::withMessages([
                        'items' => 'Return quantity must be greater than zero.',
                    ]);
                }

                if ($qty > $returnable + 0.0001) {
                    throw ValidationException::withMessages([
                        'items' => "Return qty exceeds returnable amount for a line (max {$returnable}).",
                    ]);
                }
            }

            return $this->returns->saleReturn([
                'branch_id' => $branch->id,
                'sale_id' => $sale->id,
                'return_date' => $data['return_date'],
                'notes' => $data['notes'] ?? null,
                'items' => $lines->map(fn ($row) => [
                    'sale_item_id' => (int) $row['sale_item_id'],
                    'quantity' => (float) $row['quantity'],
                ])->all(),
            ]);
        });
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'return_date', 'number', 'total'];
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
     * @return array<int, float>
     */
    protected function returnedQuantitiesForSale(int $saleId, ?SaleReturn $excludeReturn = null): array
    {
        $query = SaleReturnItem::query()
            ->whereHas('saleReturn', fn ($q) => $q->where('sale_id', $saleId));

        if ($excludeReturn) {
            $query->where('sale_return_id', '!=', $excludeReturn->id);
        }

        return $query
            ->selectRaw('sale_item_id, SUM(quantity) as total')
            ->groupBy('sale_item_id')
            ->pluck('total', 'sale_item_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeDetail(SaleReturn $doc): array
    {
        return [
            ...$this->serializeList($doc),
            'notes' => $doc->notes,
            'subtotal' => round((float) $doc->subtotal, 2),
            'tax_total' => round((float) $doc->tax_total, 2),
            'refunded_total' => round((float) $doc->refunded_total, 2),
            'branch' => $doc->branch
                ? ['id' => $doc->branch->id, 'name' => $doc->branch->name]
                : null,
            'creator' => $doc->creator
                ? ['id' => $doc->creator->id, 'name' => $doc->creator->name]
                : null,
            'items' => $doc->items->map(fn (SaleReturnItem $item) => [
                'id' => $item->id,
                'product_name' => $item->product?->name,
                'variant_name' => $item->variant?->name,
                'unit_code' => $item->unit?->code,
                'quantity' => round((float) $item->quantity, 4),
                'unit_price' => round((float) $item->unit_price, 4),
                'tax_amount' => round((float) $item->tax_amount, 2),
                'line_total' => round((float) $item->line_total, 2),
            ])->values(),
        ];
    }
}
