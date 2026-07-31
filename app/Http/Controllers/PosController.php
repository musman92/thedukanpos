<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pos\ParkSaleRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MoneySource;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Shift;
use App\Services\SaleService;
use App\Services\SettingService;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function __construct(protected SettingService $settings) {}

    public function index(SaleService $sales): Response
    {
        $branch = BranchContext::ensure();
        $shift = Shift::query()->where('branch_id', $branch->id)->open()->latest('id')->first();
        $config = $this->settings->publicConfig();

        return Inertia::render('Pos/Index', [
            'tenant' => [
                'code' => tenant('code'),
                'name' => tenant('name'),
            ],
            'branch' => $branch->only(['id', 'code', 'name']),
            'shift' => $shift,
            'parked_bills' => $sales->listParked($branch->id),
            'categories' => $this->posCategories(),
            'pos_settings' => [
                'allow_credit' => (bool) $config['pos_allow_credit'],
                'show_stock' => (bool) $config['pos_show_stock'],
                'show_product_image' => (bool) $config['pos_show_product_image'],
                'catalog_mode' => $config['pos_catalog_mode'] ?? 'flat',
                'currency_symbol' => $config['currency_symbol'],
                'currency_position' => $config['currency_position'],
                'decimal_points' => $config['decimal_points'],
            ],
            'moneySources' => MoneySource::query()
                ->forPayments()
                ->forBranch(BranchContext::ensure()->id)
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
            'customers' => Customer::query()
                ->where('is_active', true)
                ->orderByRaw("CASE WHEN UPPER(code) = ? THEN 0 ELSE 1 END", [Customer::CODE_WALK_IN])
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'phone', 'balance', 'is_system'])
                ->map(fn (Customer $customer) => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'code' => $customer->code,
                    'phone' => $customer->phone,
                    'balance' => $customer->balance,
                    'is_system' => (bool) $customer->is_system,
                    'is_walk_in' => $customer->isWalkIn(),
                ])
                ->values()
                ->all(),
            'default_customer_id' => Customer::walkIn()?->id,
        ]);
    }

    public function catalog(Request $request): JsonResponse
    {
        $branch = BranchContext::ensure();
        $categoryId = $request->input('category_id');
        $uncategorized = $request->boolean('uncategorized');
        $mode = $this->settings->publicConfig()['pos_catalog_mode'] ?? 'flat';

        $variants = ProductVariant::query()
            ->with(['product.brand', 'product.tax', 'saleUnit', 'purchaseUnit'])
            ->with(['locations' => fn ($q) => $q->where('branch_id', $branch->id)->with(['section', 'rack'])])
            ->with(['stocks' => fn ($q) => $q->where('branch_id', $branch->id)])
            ->where('product_variants.is_active', true)
            ->whereHas('product', function ($pq) use ($categoryId, $uncategorized) {
                $pq->where('is_active', true);
                if ($uncategorized) {
                    $pq->whereNull('category_id');
                } elseif ($categoryId !== null && $categoryId !== '') {
                    $pq->where('category_id', (int) $categoryId);
                }
            })
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->orderBy('products.name')
            ->orderBy('product_variants.name')
            ->select('product_variants.*')
            ->limit(200)
            ->get()
            ->map(fn (ProductVariant $variant) => $this->mapVariantForPos($variant));

        if ($mode === 'grouped') {
            return response()->json([
                'mode' => 'grouped',
                'data' => $this->groupVariantsForPos($variants),
            ]);
        }

        return response()->json([
            'mode' => 'flat',
            'data' => $variants->values()->all(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $branch = BranchContext::ensure();
        $q = $request->string('q')->toString();

        if (strlen($q) < 1) {
            return response()->json(['data' => []]);
        }

        $variants = ProductVariant::query()
            ->with(['product.brand', 'product.tax', 'saleUnit', 'purchaseUnit'])
            ->with(['locations' => fn ($query) => $query->where('branch_id', $branch->id)->with(['section', 'rack'])])
            ->with(['stocks' => fn ($query) => $query->where('branch_id', $branch->id)])
            ->where('is_active', true)
            ->whereHas('product', fn ($pq) => $pq->where('is_active', true))
            ->search($q)
            ->limit(25)
            ->get()
            ->map(fn (ProductVariant $variant) => $this->mapVariantForPos($variant));

        return response()->json([
            'mode' => 'flat',
            'data' => $variants,
        ]);
    }

    public function checkout(Request $request, SaleService $sales): JsonResponse
    {
        $branch = BranchContext::ensure();
        $shift = Shift::query()->where('branch_id', $branch->id)->open()->latest('id')->first();

        if (! $shift) {
            return response()->json(['message' => 'Open a shift before selling.'], 422);
        }

        $data = $request->validate([
            'parked_sale_id' => ['nullable', 'integer', 'exists:sales,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'foc' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.unit_id' => ['nullable', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['nullable', 'array'],
            'payments.*.money_source_id' => ['required', 'exists:money_sources,id'],
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $isFoc = (bool) ($data['foc'] ?? false);
        $payments = collect($data['payments'] ?? [])->filter(fn ($p) => (float) $p['amount'] > 0)->values()->all();
        $paidSum = collect($payments)->sum(fn ($p) => (float) $p['amount']);

        $walkIn = Customer::walkIn();
        $customerId = isset($data['customer_id']) ? (int) $data['customer_id'] : null;
        if (! $customerId && $walkIn) {
            $customerId = (int) $walkIn->id;
        }
        $isWalkInCustomer = $walkIn && $customerId === (int) $walkIn->id;
        $creditCustomerId = $isWalkInCustomer ? null : $customerId;

        if ($isFoc) {
            $payments = [];
            $paidSum = 0;
        } elseif ($paidSum <= 0 && ! $creditCustomerId) {
            return response()->json(['message' => 'Add a payment or select a customer for credit.'], 422);
        }

        // Full credit / unpaid remainder needs credit enabled (FOC is not credit).
        if (! $isFoc && ($paidSum <= 0 || $creditCustomerId)) {
            if (! $this->settings->allowPosCredit() && $paidSum <= 0) {
                return response()->json(['message' => 'Credit sales are disabled in settings.'], 422);
            }
        }

        try {
            $payload = [
                'branch_id' => $branch->id,
                'shift_id' => $shift->id,
                'customer_id' => $customerId,
                'discount_total' => $data['discount_total'] ?? 0,
                'notes' => $isFoc ? trim(($data['notes'] ?? '').' FOC') : ($data['notes'] ?? null),
                'items' => $data['items'],
                'payments' => $payments,
                'allow_credit' => $isFoc ? true : $this->settings->allowPosCredit(),
            ];

            if (! empty($data['parked_sale_id'])) {
                $parked = Sale::query()
                    ->where('branch_id', $branch->id)
                    ->where('status', Sale::STATUS_PARKED)
                    ->findOrFail((int) $data['parked_sale_id']);
                $sale = $sales->completeParked($parked, $payload);
            } else {
                $sale = $sales->checkout($payload);
            }
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'sale' => $sale,
            'message' => "Sale {$sale->number} completed.",
        ]);
    }

    public function parked(SaleService $sales): JsonResponse
    {
        $branch = BranchContext::ensure();

        return response()->json([
            'data' => $sales->listParked($branch->id),
        ]);
    }

    public function today(SaleService $sales): JsonResponse
    {
        $branch = BranchContext::ensure();

        return response()->json([
            'data' => $sales->listToday($branch->id),
        ]);
    }

    public function todayShow(Sale $sale, SaleService $sales): JsonResponse
    {
        $branch = BranchContext::ensure();

        if ((int) $sale->branch_id !== (int) $branch->id || $sale->isParked()) {
            return response()->json(['message' => 'Sale not found.'], 404);
        }

        return response()->json([
            'sale' => $sales->posSaleDetail($sale),
        ]);
    }

    public function todayVoid(Sale $sale, SaleService $sales): JsonResponse
    {
        $branch = BranchContext::ensure();

        if ((int) $sale->branch_id !== (int) $branch->id || $sale->isParked()) {
            return response()->json(['message' => 'Sale not found.'], 404);
        }

        try {
            $voided = $sales->voidSale($sale);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'sale' => $sales->posSaleDetail($voided),
            'message' => "Sale {$voided->number} cancelled.",
        ]);
    }

    public function park(ParkSaleRequest $request, SaleService $sales): JsonResponse
    {
        $branch = BranchContext::ensure();
        $shift = Shift::query()->where('branch_id', $branch->id)->open()->latest('id')->first();

        if (! $shift) {
            return response()->json(['message' => 'Open a shift before saving a bill.'], 422);
        }

        $data = $request->validated();

        try {
            $sale = $sales->park([
                'branch_id' => $branch->id,
                'shift_id' => $shift->id,
                'customer_id' => $data['customer_id'] ?? null,
                'discount_total' => $data['discount_total'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'items' => $data['items'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'sale' => $sales->serializeParked($sale),
            'message' => "Bill {$sale->number} saved for later.",
        ]);
    }

    public function updateParked(ParkSaleRequest $request, Sale $sale, SaleService $sales): JsonResponse
    {
        $branch = BranchContext::ensure();

        if ((int) $sale->branch_id !== (int) $branch->id || ! $sale->isParked()) {
            return response()->json(['message' => 'Parked bill not found.'], 404);
        }

        $data = $request->validated();

        try {
            $sale = $sales->updateParked($sale, [
                'customer_id' => $data['customer_id'] ?? null,
                'discount_total' => $data['discount_total'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'items' => $data['items'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'sale' => $sales->serializeParked($sale),
            'message' => "Bill {$sale->number} updated.",
        ]);
    }

    public function discardParked(Sale $sale, SaleService $sales): JsonResponse
    {
        $branch = BranchContext::ensure();

        if ((int) $sale->branch_id !== (int) $branch->id || ! $sale->isParked()) {
            return response()->json(['message' => 'Parked bill not found.'], 404);
        }

        try {
            $sales->discardParked($sale);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Parked bill discarded.']);
    }

    public function receipt(Sale $sale): Response
    {
        if ($sale->isParked()) {
            abort(404);
        }

        $sale->load(['items.product', 'items.variant', 'payments.moneySource', 'branch', 'cashier', 'customer']);

        $branding = $this->settings->receiptBranding($sale->branch?->name);

        return Inertia::render('Pos/Receipt', [
            'sale' => [
                'id' => $sale->id,
                'number' => $sale->number,
                'created_at' => format_company_datetime($sale->created_at),
                'subtotal' => (float) $sale->subtotal,
                'tax_total' => (float) $sale->tax_total,
                'discount_total' => (float) $sale->discount_total,
                'total' => (float) $sale->total,
                'paid_total' => (float) $sale->paid_total,
                'customer' => $sale->customer
                    ? ['id' => $sale->customer->id, 'name' => $sale->customer->name]
                    : null,
                'cashier' => $sale->cashier
                    ? ['id' => $sale->cashier->id, 'name' => $sale->cashier->name ?: $sale->cashier->username]
                    : null,
                'branch' => $sale->branch
                    ? ['id' => $sale->branch->id, 'name' => $sale->branch->name]
                    : null,
                'items' => $sale->items->map(fn ($item) => [
                    'id' => $item->id,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => (float) $item->line_total,
                    'tax_rate' => (float) ($item->tax_rate ?? 0),
                    'product' => $item->product ? ['name' => $item->product->name] : null,
                    'variant' => $item->variant
                        ? ['name' => $item->variant->name]
                        : null,
                ]),
                'payments' => $sale->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'amount' => (float) $p->amount,
                    'money_source' => $p->moneySource
                        ? ['name' => $p->moneySource->name]
                        : null,
                ]),
            ],
            'tenant' => [
                'code' => tenant('code'),
                'name' => tenant('name'),
            ],
            'branding' => $branding,
        ]);
    }

    /**
     * @return list<array{id:int|string, name:string, count:int}>
     */
    protected function posCategories(): array
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->withCount([
                'products as active_products_count' => fn ($q) => $q
                    ->where('is_active', true)
                    ->whereHas('variants', fn ($vq) => $vq->where('is_active', true)),
            ])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->filter(fn (Category $category) => (int) $category->active_products_count > 0)
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'count' => (int) $category->active_products_count,
            ])
            ->values()
            ->all();

        $uncategorized = ProductVariant::query()
            ->where('is_active', true)
            ->whereHas('product', fn ($pq) => $pq->where('is_active', true)->whereNull('category_id'))
            ->count();

        $allCount = ProductVariant::query()
            ->where('is_active', true)
            ->whereHas('product', fn ($pq) => $pq->where('is_active', true))
            ->count();

        $list = [
            ['id' => 'all', 'name' => 'All products', 'count' => $allCount],
        ];

        if ($uncategorized > 0) {
            $list[] = ['id' => 'uncategorized', 'name' => 'Uncategorized', 'count' => $uncategorized];
        }

        return array_merge($list, $categories);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapVariantForPos(ProductVariant $variant): array
    {
        $location = $variant->locations->first();
        $stock = $variant->stocks->first();

        return [
            'id' => $variant->id,
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'name' => $variant->displayName(),
            'product_name' => $variant->product?->name ?? 'Product',
            'variant_name' => $variant->name,
            'short_code' => $variant->short_code,
            'barcode' => $variant->barcode,
            'brand' => $variant->product?->brand?->name,
            'image_url' => $variant->product?->image_url,
            'sale_price' => (float) $variant->sale_price,
            'conversion_rate' => (float) $variant->conversion_rate,
            'sale_unit' => $variant->saleUnit?->only(['id', 'name', 'code']),
            'purchase_unit' => $variant->purchaseUnit?->only(['id', 'name', 'code']),
            'tax' => $variant->product?->tax?->only(['id', 'name', 'rate', 'is_inclusive']),
            'stock' => $stock ? (float) $stock->quantity : 0,
            'location' => $location?->label(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $variants
     * @return list<array<string, mixed>>
     */
    protected function groupVariantsForPos($variants): array
    {
        $groups = [];

        foreach ($variants as $variant) {
            $productId = (int) ($variant['product_id'] ?? 0);
            if (! isset($groups[$productId])) {
                $groups[$productId] = [
                    'id' => 'p-'.$productId,
                    'kind' => 'product',
                    'product_id' => $productId,
                    'name' => $variant['product_name'] ?? $variant['name'],
                    'image_url' => $variant['image_url'] ?? null,
                    'brand' => $variant['brand'] ?? null,
                    'variants' => [],
                ];
            }
            $groups[$productId]['variants'][] = array_merge($variant, ['kind' => 'variant']);
        }

        return collect($groups)
            ->map(function (array $group) {
                $prices = collect($group['variants'])->pluck('sale_price')->map(fn ($p) => (float) $p);
                $stocks = collect($group['variants'])->pluck('stock')->map(fn ($s) => (float) $s);
                $min = $prices->min() ?? 0;
                $max = $prices->max() ?? 0;
                $first = $group['variants'][0] ?? null;

                return [
                    ...$group,
                    'variant_count' => count($group['variants']),
                    'sale_price' => $min,
                    'price_min' => $min,
                    'price_max' => $max,
                    'stock' => $stocks->sum(),
                    // Single-variant products can be added like a flat tile.
                    'variant_id' => count($group['variants']) === 1 ? ($first['variant_id'] ?? null) : null,
                    'sale_unit' => count($group['variants']) === 1 ? ($first['sale_unit'] ?? null) : null,
                    'tax' => $first['tax'] ?? null,
                    'location' => count($group['variants']) === 1 ? ($first['location'] ?? null) : null,
                ];
            })
            ->values()
            ->all();
    }
}
