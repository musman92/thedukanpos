<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\ProductVariant;
use App\Models\StockTransfer;
use App\Support\BranchContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function __construct(
        protected StockDocumentService $documents,
        protected InventoryService $inventory,
    ) {}

    /**
     * @param  array{
     *   q?:string|null,
     *   to_branch_id?:int|string|null,
     *   from?:string|null,
     *   to?:string|null,
     *   per_page?:int|string|null,
     *   sort?:string|null,
     *   direction?:string|null
     * }  $filters
     * @return array{
     *   transfers: LengthAwarePaginator,
     *   filters: array<string, mixed>,
     *   variants: Collection,
     *   branches: Collection,
     *   branch: array{id:int,name:string}|null
     * }
     */
    public function paginate(array $filters = []): array
    {
        $branch = BranchContext::ensure();
        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        $toBranchId = $filters['to_branch_id'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        $transfers = StockTransfer::query()
            ->with([
                'fromBranch:id,name',
                'toBranch:id,name',
                'creator:id,name,username',
                'items.variant.product:id,name',
                'items.variant.saleUnit:id,name',
            ])
            ->where(function (Builder $query) use ($branch) {
                $query->where('from_branch_id', $branch->id)
                    ->orWhere('to_branch_id', $branch->id);
            })
            ->when($toBranchId, fn (Builder $query) => $query->where('to_branch_id', (int) $toBranchId))
            ->when($q !== '', function (Builder $query) use ($q) {
                $query->where(function (Builder $inner) use ($q) {
                    $inner->where('number', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%")
                        ->orWhereHas('items.variant', fn (Builder $v) => $v->search($q))
                        ->orWhereHas('fromBranch', fn (Builder $b) => $b->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('toBranch', fn (Builder $b) => $b->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($from, fn (Builder $query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('created_at', '<=', $to))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn (Builder $query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (StockTransfer $transfer) => $this->serialize($transfer, $branch->id));

        return [
            'transfers' => $transfers,
            'filters' => [
                'q' => $q,
                'to_branch_id' => $toBranchId ? (string) $toBranchId : '',
                'from' => $from ? (string) $from : '',
                'to' => $to ? (string) $to : '',
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'variants' => $this->variantOptions($branch->id),
            'branches' => Branch::query()
                ->where('is_active', true)
                ->where('id', '!=', $branch->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'branch' => $branch->only(['id', 'name']),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function variantOptions(int $branchId): Collection
    {
        $stocks = BranchStock::query()
            ->where('branch_id', $branchId)
            ->pluck('quantity', 'variant_id');

        return ProductVariant::query()
            ->with([
                'product:id,name,track_stock',
                'saleUnit:id,name',
            ])
            ->where('is_active', true)
            ->whereHas('product', fn (Builder $p) => $p->where('track_stock', true))
            ->orderBy('short_code')
            ->get()
            ->map(function (ProductVariant $v) use ($stocks) {
                $onHand = round((float) ($stocks[$v->id] ?? 0), 4);

                return [
                    'value' => $v->id,
                    'label' => $v->displayName(),
                    'meta' => $v->short_code,
                    'id' => $v->id,
                    'short_code' => $v->short_code,
                    'sale_unit_name' => $v->saleUnit?->name ?? 'pcs',
                    'quantity_on_hand' => $onHand,
                ];
            });
    }

    /**
     * @param  array{
     *   to_branch_id:int,
     *   notes?:string|null,
     *   items: list<array{variant_id:int, quantity:float}>
     * }  $data
     */
    public function create(array $data): StockTransfer
    {
        $from = BranchContext::ensure();

        if ((int) $data['to_branch_id'] === (int) $from->id) {
            throw ValidationException::withMessages([
                'to_branch_id' => 'Choose a different destination branch.',
            ]);
        }

        $toExists = Branch::query()
            ->whereKey((int) $data['to_branch_id'])
            ->where('is_active', true)
            ->exists();

        if (! $toExists) {
            throw ValidationException::withMessages([
                'to_branch_id' => 'Destination branch is not available.',
            ]);
        }

        $items = $this->normalizeItems($data['items'] ?? []);
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one item to transfer.',
            ]);
        }

        try {
            return $this->documents->transfer([
                'from_branch_id' => $from->id,
                'to_branch_id' => (int) $data['to_branch_id'],
                'notes' => $data['notes'] ?? null,
                'items' => $items,
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages([
                'items' => $e->getMessage(),
            ]);
        }
    }

    public function delete(StockTransfer $transfer): void
    {
        $branch = BranchContext::ensure();

        if ((int) $transfer->from_branch_id !== (int) $branch->id
            && (int) $transfer->to_branch_id !== (int) $branch->id) {
            throw ValidationException::withMessages([
                'transfer' => 'This transfer does not involve the current branch.',
            ]);
        }

        try {
            $this->documents->reverseTransfer($transfer);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages([
                'transfer' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  list<array{variant_id?:mixed, quantity?:mixed}>  $items
     * @return list<array{variant_id:int, quantity:float}>
     */
    protected function normalizeItems(array $items): array
    {
        $normalized = [];
        $seen = [];

        foreach ($items as $index => $row) {
            $variantId = (int) ($row['variant_id'] ?? 0);
            $qty = round((float) ($row['quantity'] ?? 0), 4);

            if ($variantId < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.variant_id" => 'Select a product.',
                ]);
            }

            if ($qty < 0.0001) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => 'Quantity must be greater than zero.',
                ]);
            }

            if (isset($seen[$variantId])) {
                throw ValidationException::withMessages([
                    "items.{$index}.variant_id" => 'This product is already on the transfer. Combine quantities on one line.',
                ]);
            }

            $seen[$variantId] = true;
            $normalized[] = [
                'variant_id' => $variantId,
                'quantity' => $qty,
            ];
        }

        return $normalized;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'created_at', 'number'];
        $sort = strtolower(trim((string) ($sort ?? 'created_at')));
        if (! in_array($sort, $allowed, true)) {
            $sort = 'created_at';
        }

        $direction = strtolower(trim((string) ($direction ?? 'desc')));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        return [$sort, $direction];
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(StockTransfer $transfer, ?int $currentBranchId = null): array
    {
        $items = $transfer->relationLoaded('items') ? $transfer->items : collect();
        $totalQty = round((float) $items->sum(fn ($i) => (float) $i->quantity), 4);

        $direction = null;
        if ($currentBranchId !== null) {
            if ((int) $transfer->from_branch_id === $currentBranchId) {
                $direction = 'out';
            } elseif ((int) $transfer->to_branch_id === $currentBranchId) {
                $direction = 'in';
            }
        }

        return [
            'id' => $transfer->id,
            'number' => $transfer->number,
            'status' => $transfer->status,
            'notes' => $transfer->notes,
            'created_at' => $transfer->created_at?->format('Y-m-d H:i'),
            'direction' => $direction,
            'from_branch' => $transfer->fromBranch
                ? ['id' => $transfer->fromBranch->id, 'name' => $transfer->fromBranch->name]
                : null,
            'to_branch' => $transfer->toBranch
                ? ['id' => $transfer->toBranch->id, 'name' => $transfer->toBranch->name]
                : null,
            'creator' => $transfer->creator
                ? [
                    'id' => $transfer->creator->id,
                    'name' => $transfer->creator->name ?: $transfer->creator->username,
                ]
                : null,
            'items_count' => $items->count(),
            'total_qty' => $totalQty,
            'items' => $items->map(function ($item) {
                $variant = $item->variant;

                return [
                    'id' => $item->id,
                    'variant_id' => $item->variant_id,
                    'quantity' => round((float) $item->quantity, 4),
                    'unit_name' => $variant?->saleUnit?->name ?? 'pcs',
                    'variant' => $variant
                        ? [
                            'id' => $variant->id,
                            'name' => $variant->displayName(),
                            'short_code' => $variant->short_code,
                        ]
                        : null,
                ];
            })->values()->all(),
            'can_delete' => $currentBranchId !== null
                && (int) $transfer->from_branch_id === (int) $currentBranchId,
        ];
    }
}
