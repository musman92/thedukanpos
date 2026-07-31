<?php

namespace App\Services;

use App\Models\BranchStock;
use App\Models\ProductVariant;
use App\Models\StockDamage;
use App\Support\BranchContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockDamageService
{
    public const REASONS = [
        'expired' => 'Expired',
        'damaged' => 'Damaged / broken',
        'leakage' => 'Delivery leakage',
        'fault' => 'Product fault',
        'other' => 'Other',
    ];

    public function __construct(
        protected StockDocumentService $documents,
        protected InventoryService $inventory,
    ) {}

    /**
     * @return list<array{value:string,label:string}>
     */
    public function reasonOptions(): array
    {
        return collect(self::REASONS)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @param  array{
     *   q?:string|null,
     *   reason?:string|null,
     *   from?:string|null,
     *   to?:string|null,
     *   per_page?:int|string|null,
     *   sort?:string|null,
     *   direction?:string|null
     * }  $filters
     * @return array{
     *   damages: LengthAwarePaginator,
     *   filters: array<string, mixed>,
     *   variants: Collection,
     *   reasons: list<array{value:string,label:string}>,
     *   branch: array{id:int,name:string}|null
     * }
     */
    public function paginate(array $filters = []): array
    {
        $branch = BranchContext::ensure();
        $companyDefault = company_page_limit();
        $perPage = resolve_page_limit($filters['per_page'] ?? null, $companyDefault);
        $q = trim((string) ($filters['q'] ?? ''));
        $reason = trim((string) ($filters['reason'] ?? ''));
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        [$sort, $direction] = $this->resolveSort(
            $filters['sort'] ?? null,
            $filters['direction'] ?? null,
        );

        if ($reason !== '' && ! array_key_exists($reason, self::REASONS)) {
            $reason = '';
        }

        $damages = StockDamage::query()
            ->with([
                'branch:id,name',
                'creator:id,name,username',
                'items.variant.product:id,name',
                'items.variant.saleUnit:id,name',
            ])
            ->where('branch_id', $branch->id)
            ->when($reason !== '', fn (Builder $query) => $query->where('reason', $reason))
            ->when($q !== '', function (Builder $query) use ($q) {
                $query->where(function (Builder $inner) use ($q) {
                    $inner->where('number', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%")
                        ->orWhere('reason', 'like', "%{$q}%")
                        ->orWhereHas('items.variant', fn (Builder $v) => $v->search($q));
                });
            })
            ->when($from, fn (Builder $query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('created_at', '<=', $to))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn (Builder $query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (StockDamage $damage) => $this->serialize($damage));

        return [
            'damages' => $damages,
            'filters' => [
                'q' => $q,
                'reason' => $reason,
                'from' => $from ? (string) $from : '',
                'to' => $to ? (string) $to : '',
                'per_page' => $perPage,
                'company_page_limit' => $companyDefault,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'variants' => $this->variantOptions($branch->id),
            'reasons' => $this->reasonOptions(),
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
            ->get(['variant_id', 'quantity', 'average_cost'])
            ->keyBy('variant_id');

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
                $stock = $stocks->get($v->id);

                return [
                    'value' => $v->id,
                    'label' => $v->displayName(),
                    'meta' => $v->short_code,
                    'id' => $v->id,
                    'short_code' => $v->short_code,
                    'sale_unit_name' => $v->saleUnit?->name ?? 'pcs',
                    'quantity_on_hand' => round((float) ($stock?->quantity ?? 0), 4),
                    'average_cost' => round((float) ($stock?->average_cost ?? 0), 4),
                ];
            });
    }

    /**
     * @param  array{
     *   reason:string,
     *   notes?:string|null,
     *   items: list<array{variant_id:int, quantity:float}>
     * }  $data
     */
    public function create(array $data): StockDamage
    {
        $branch = BranchContext::ensure();
        $reason = $this->assertReason($data['reason'] ?? null);
        $notes = $this->assertNotes($reason, $data['notes'] ?? null);
        $items = $this->normalizeItems($data['items'] ?? []);

        try {
            return $this->documents->damage([
                'branch_id' => $branch->id,
                'reason' => $reason,
                'notes' => $notes,
                'items' => $items,
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages([
                'items' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{
     *   reason:string,
     *   notes?:string|null,
     *   items: list<array{variant_id:int, quantity:float}>
     * }  $data
     */
    public function update(StockDamage $damage, array $data): StockDamage
    {
        $branch = BranchContext::ensure();
        if ((int) $damage->branch_id !== (int) $branch->id) {
            throw ValidationException::withMessages([
                'damage' => 'This damage record belongs to another branch.',
            ]);
        }

        try {
            return DB::transaction(function () use ($damage, $data) {
                $this->documents->reverseDamage($damage);

                return $this->create($data);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages([
                'items' => $e->getMessage(),
            ]);
        }
    }

    public function delete(StockDamage $damage): void
    {
        $branch = BranchContext::ensure();
        if ((int) $damage->branch_id !== (int) $branch->id) {
            throw ValidationException::withMessages([
                'damage' => 'This damage record belongs to another branch.',
            ]);
        }

        try {
            $this->documents->reverseDamage($damage);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages([
                'damage' => $e->getMessage(),
            ]);
        }
    }

    public function findForEdit(int $id): ?array
    {
        $branch = BranchContext::ensure();
        $damage = StockDamage::query()
            ->with([
                'branch:id,name',
                'creator:id,name,username',
                'items.variant.product:id,name',
                'items.variant.saleUnit:id,name',
            ])
            ->where('branch_id', $branch->id)
            ->find($id);

        return $damage ? $this->serialize($damage) : null;
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
                    "items.{$index}.variant_id" => 'This product is already listed. Combine quantities on one line.',
                ]);
            }

            $seen[$variantId] = true;
            $normalized[] = [
                'variant_id' => $variantId,
                'quantity' => $qty,
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one item.',
            ]);
        }

        return $normalized;
    }

    protected function assertReason(mixed $reason): string
    {
        $reason = strtolower(trim((string) $reason));
        if (! array_key_exists($reason, self::REASONS)) {
            throw ValidationException::withMessages([
                'reason' => 'Select a valid damage reason.',
            ]);
        }

        return $reason;
    }

    protected function assertNotes(string $reason, mixed $notes): ?string
    {
        $notes = trim((string) ($notes ?? ''));
        if ($reason === 'other' && $notes === '') {
            throw ValidationException::withMessages([
                'notes' => 'Notes are required when reason is Other.',
            ]);
        }

        return $notes !== '' ? $notes : null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['id', 'created_at', 'number', 'reason'];
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
    public function serialize(StockDamage $damage): array
    {
        $items = $damage->relationLoaded('items') ? $damage->items : collect();
        $totalQty = round((float) $items->sum(fn ($i) => (float) $i->quantity), 4);
        $totalCost = round((float) $items->sum(
            fn ($i) => (float) $i->quantity * (float) ($i->unit_cost ?? 0)
        ), 2);

        $serializedItems = $items->map(function ($item) {
            $variant = $item->variant;

            return [
                'id' => $item->id,
                'variant_id' => $item->variant_id,
                'quantity' => round((float) $item->quantity, 4),
                'unit_cost' => round((float) ($item->unit_cost ?? 0), 4),
                'unit_name' => $variant?->saleUnit?->name ?? 'pcs',
                'display_name' => $variant?->displayName() ?? '—',
                'short_code' => $variant?->short_code,
                'variant' => $variant
                    ? [
                        'id' => $variant->id,
                        'name' => $variant->displayName(),
                        'short_code' => $variant->short_code,
                    ]
                    : null,
            ];
        })->values()->all();

        return [
            'id' => $damage->id,
            'number' => $damage->number,
            'reason' => $damage->reason,
            'reason_label' => self::REASONS[$damage->reason] ?? $damage->reason,
            'notes' => $damage->notes,
            'created_at' => $damage->created_at?->format('Y-m-d H:i'),
            'branch' => $damage->branch
                ? ['id' => $damage->branch->id, 'name' => $damage->branch->name]
                : null,
            'creator' => $damage->creator
                ? [
                    'id' => $damage->creator->id,
                    'name' => $damage->creator->name ?: $damage->creator->username,
                ]
                : null,
            'items_count' => $items->count(),
            'total_qty' => $totalQty,
            'total_cost' => $totalCost,
            'items' => $serializedItems,
        ];
    }
}
