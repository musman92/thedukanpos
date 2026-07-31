<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\Variation;
use App\Models\VariationOption;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Multi-sheet Excel import/export for products (FoodPOS-style).
 *
 * Sheet "products" — one row per product (link key: product_code).
 * Sheet "variants" — one row per SKU option (link key: product_code + option).
 */
class ProductImportExportService
{
    /** @var list<string> */
    public const PRODUCT_HEADERS = [
        'product_code',
        'name',
        'type',
        'brand',
        'category',
        'tax',
        'variation_code',
        'barcode',
        'purchase_unit',
        'sale_unit',
        'conversion_rate',
        'sale_price',
        'purchase_price',
        'min_qty_alert',
        'track_stock',
        'track_serial',
        'is_active',
        'notes',
    ];

    /** @var list<string> */
    public const VARIANT_HEADERS = [
        'product_code',
        'option_code',
        'option_name',
        'short_code',
        'barcode',
        'purchase_unit',
        'sale_unit',
        'conversion_rate',
        'sale_price',
        'purchase_price',
        'track_serial',
        'is_active',
    ];

    /** @var list<array<string, string>> */
    public const PRODUCT_SAMPLE_ROWS = [
        [
            'product_code' => 'P01',
            'name' => 'Mineral Water',
            'type' => 'single',
            'brand' => 'Local',
            'category' => 'Beverages',
            'tax' => 'GST',
            'variation_code' => '',
            'barcode' => '1234567890123',
            'purchase_unit' => 'ctn',
            'sale_unit' => 'pcs',
            'conversion_rate' => '24',
            'sale_price' => '40',
            'purchase_price' => '30',
            'min_qty_alert' => '10',
            'track_stock' => '1',
            'track_serial' => '0',
            'is_active' => '1',
            'notes' => '',
        ],
        [
            'product_code' => 'P02',
            'name' => 'Pepsi',
            'type' => 'variant',
            'brand' => 'Pepsi',
            'category' => 'Beverages',
            'tax' => 'GST',
            'variation_code' => 'V01',
            'barcode' => '',
            'purchase_unit' => 'ctn',
            'sale_unit' => 'pcs',
            'conversion_rate' => '24',
            'sale_price' => '80',
            'purchase_price' => '60',
            'min_qty_alert' => '12',
            'track_stock' => '1',
            'track_serial' => '0',
            'is_active' => '1',
            'notes' => 'Sizes from Variations master',
        ],
    ];

    /** @var list<array<string, string>> */
    public const VARIANT_SAMPLE_ROWS = [
        [
            'product_code' => 'P02',
            'option_code' => '',
            'option_name' => '500ml',
            'short_code' => 'PEP500',
            'barcode' => '1234567890124',
            'purchase_unit' => 'ctn',
            'sale_unit' => 'pcs',
            'conversion_rate' => '24',
            'sale_price' => '80',
            'purchase_price' => '60',
            'track_serial' => '0',
            'is_active' => '1',
        ],
        [
            'product_code' => 'P02',
            'option_code' => '',
            'option_name' => '1.5L',
            'short_code' => 'PEP15',
            'barcode' => '',
            'purchase_unit' => 'ctn',
            'sale_unit' => 'pcs',
            'conversion_rate' => '12',
            'sale_price' => '150',
            'purchase_price' => '110',
            'track_serial' => '0',
            'is_active' => '1',
        ],
    ];

    public const MAX_ROWS = 1000;

    /** @var array<string, list<string>> */
    private const SHEET_ALIASES = [
        'products' => ['products', 'product', 'items'],
        'variants' => ['variants', 'variant', 'options', 'skus'],
    ];

    /**
     * @return BinaryFileResponse
     */
    public function export(string $format = 'xlsx')
    {
        // Multi-sheet workbook — Excel only (csv format ignored).
        return $this->exportExcel();
    }

    /**
     * @return BinaryFileResponse
     */
    public function sample(string $format = 'xlsx')
    {
        return $this->sampleExcel();
    }

    /**
     * @return array{created:int, updated:int, skipped:int, errors:list<array{row:string, message:string}>, entity:string}
     */
    public function import(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [[
                    'row' => 'file',
                    'message' => 'Products import requires an Excel workbook (.xlsx) with sheets: products, variants.',
                ]],
                'entity' => 'products',
            ];
        }

        $parsed = $this->parseWorkbook($file);
        $result = $this->upsertWorkbook($parsed['products'], $parsed['variants'], $parsed['errors']);
        $result['entity'] = 'products';

        return $result;
    }

    protected function exportExcel(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $productSheet = $spreadsheet->getActiveSheet();
        $this->fillSheet($productSheet, 'products', self::PRODUCT_HEADERS, []);

        $variantSheet = $spreadsheet->createSheet();
        $this->fillSheet($variantSheet, 'variants', self::VARIANT_HEADERS, []);

        $productRow = 2;
        $variantRow = 2;

        Product::query()
            ->with(['brand', 'category', 'tax', 'purchaseUnit', 'saleUnit', 'variation', 'variants.purchaseUnit', 'variants.saleUnit', 'variants.variationOption'])
            ->orderBy('id')
            ->chunk(100, function ($products) use ($productSheet, $variantSheet, &$productRow, &$variantRow) {
                foreach ($products as $product) {
                    $type = $product->type ?: 'single';
                    $productSheet->fromArray([[
                        $product->short_code,
                        $product->name,
                        $type,
                        $product->brand?->name,
                        $product->category?->name,
                        $product->tax?->code,
                        $product->variation?->code,
                        $product->barcode,
                        $product->purchaseUnit?->code,
                        $product->saleUnit?->code,
                        $product->conversion_rate,
                        $product->sale_price,
                        $product->cost_per_unit,
                        $product->min_qty_alert,
                        $product->track_stock ? 1 : 0,
                        ($type === 'single' && $product->variants->first()?->track_serial) ? 1 : 0,
                        $product->is_active ? 1 : 0,
                        $product->notes,
                    ]], null, 'A'.$productRow);
                    $productRow++;

                    if ($type !== 'variant') {
                        continue;
                    }

                    foreach ($product->variants as $variant) {
                        $variantSheet->fromArray([[
                            $product->short_code,
                            $variant->variationOption?->code,
                            $variant->variationOption?->name ?: $variant->name,
                            $variant->short_code,
                            $variant->barcode,
                            $variant->purchaseUnit?->code,
                            $variant->saleUnit?->code,
                            $variant->conversion_rate,
                            $variant->sale_price,
                            $variant->cost_per_unit,
                            $variant->track_serial ? 1 : 0,
                            $variant->is_active ? 1 : 0,
                        ]], null, 'A'.$variantRow);
                        $variantRow++;
                    }
                }
            });

        $this->autosize($productSheet, count(self::PRODUCT_HEADERS));
        $this->autosize($variantSheet, count(self::VARIANT_HEADERS));
        $spreadsheet->setActiveSheetIndex(0);

        return $this->downloadSpreadsheet($spreadsheet, 'products-'.now()->format('Ymd-His').'.xlsx');
    }

    protected function sampleExcel(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $this->fillSheet(
            $spreadsheet->getActiveSheet(),
            'products',
            self::PRODUCT_HEADERS,
            array_map(fn ($row) => array_map(fn ($h) => $row[$h] ?? '', self::PRODUCT_HEADERS), self::PRODUCT_SAMPLE_ROWS),
        );
        $this->fillSheet(
            $spreadsheet->createSheet(),
            'variants',
            self::VARIANT_HEADERS,
            array_map(fn ($row) => array_map(fn ($h) => $row[$h] ?? '', self::VARIANT_HEADERS), self::VARIANT_SAMPLE_ROWS),
        );
        $spreadsheet->setActiveSheetIndex(0);

        return $this->downloadSpreadsheet($spreadsheet, 'products-import-sample.xlsx');
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    protected function fillSheet(Worksheet $sheet, string $title, array $headers, array $rows): void
    {
        $sheet->setTitle($title);
        $sheet->fromArray($headers, null, 'A1');
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A1:'.$lastCol.'1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E7E5E4'],
            ],
        ]);

        foreach ($rows as $i => $row) {
            $sheet->fromArray([$row], null, 'A'.($i + 2));
        }

        $this->autosize($sheet, count($headers));
    }

    protected function autosize(Worksheet $sheet, int $columnCount): void
    {
        for ($i = 1; $i <= $columnCount; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
    }

    /**
     * @return array{products: list<array<string, mixed>>, variants: list<array<string, mixed>>, errors: list<array{row:string, message:string}>}
     */
    protected function parseWorkbook(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $errors = [];
        $byKey = [];

        foreach ($spreadsheet->getAllSheets() as $index => $sheet) {
            $title = $this->normalizeSheetTitle($sheet->getTitle());
            $key = $this->resolveSheetKey($title, $index);
            if (! $key) {
                continue;
            }

            $raw = $sheet->toArray(null, true, true, false);
            if ($raw === []) {
                $byKey[$key] = ['header' => [], 'rows' => []];

                continue;
            }

            $header = $this->normalizeHeader(array_shift($raw) ?: []);
            $rows = [];
            foreach ($raw as $row) {
                if (! is_array($row) || $this->rowIsEmpty($row)) {
                    continue;
                }
                $rows[] = $row;
            }
            $byKey[$key] = ['header' => $header, 'rows' => $rows];
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (! isset($byKey['products'])) {
            $errors[] = [
                'row' => 'workbook',
                'message' => 'Missing "products" sheet. Download the sample workbook for the expected layout.',
            ];

            return ['products' => [], 'variants' => [], 'errors' => $errors];
        }

        $products = $this->mapSheetRows(
            'products',
            self::PRODUCT_HEADERS,
            $byKey['products']['header'],
            $byKey['products']['rows'],
            $errors,
            [],
        );

        $variants = $this->mapSheetRows(
            'variants',
            self::VARIANT_HEADERS,
            $byKey['variants']['header'] ?? [],
            $byKey['variants']['rows'] ?? [],
            $errors,
            ['product_code'],
        );

        return compact('products', 'variants', 'errors');
    }

    /**
     * @param  list<string>  $canonicalHeaders
     * @param  list<string>  $header
     * @param  list<list<mixed>>  $rows
     * @param  list<array{row:string, message:string}>  $errors
     * @param  list<string>  $carryFields
     * @return list<array<string, mixed>>
     */
    protected function mapSheetRows(
        string $sheetKey,
        array $canonicalHeaders,
        array $header,
        array $rows,
        array &$errors,
        array $carryFields,
    ): array {
        if ($header === [] && $rows === []) {
            return [];
        }

        $mapped = [];
        $carry = array_fill_keys($carryFields, '');

        foreach ($rows as $index => $row) {
            $excelRow = $index + 2;
            if (count($rows) > self::MAX_ROWS && $index >= self::MAX_ROWS) {
                $errors[] = [
                    'row' => "{$sheetKey}:{$excelRow}",
                    'message' => 'Import limit reached ('.self::MAX_ROWS.' rows per sheet).',
                ];
                break;
            }

            $data = @array_combine($header, array_pad($row, count($header), null));
            if (! is_array($data)) {
                $errors[] = ['row' => "{$sheetKey}:{$excelRow}", 'message' => 'Could not read this row.'];

                continue;
            }

            $normalized = [];
            foreach ($canonicalHeaders as $col) {
                $normalized[$col] = trim((string) ($data[$col] ?? ''));
            }

            foreach ($carryFields as $field) {
                if ($normalized[$field] !== '') {
                    $carry[$field] = $normalized[$field];
                } else {
                    $normalized[$field] = $carry[$field];
                }
            }

            $normalized['_row'] = "{$sheetKey}:{$excelRow}";
            $mapped[] = $normalized;
        }

        return $mapped;
    }

    /**
     * @param  list<array<string, mixed>>  $productRows
     * @param  list<array<string, mixed>>  $variantRows
     * @param  list<array{row:string, message:string}>  $errors
     * @return array{created:int, updated:int, skipped:int, errors:list<array{row:string, message:string}>}
     */
    protected function upsertWorkbook(array $productRows, array $variantRows, array $errors): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        $variantsByProduct = [];
        foreach ($variantRows as $row) {
            $code = strtoupper(trim((string) ($row['product_code'] ?? '')));
            if ($code === '') {
                $skipped++;
                $errors[] = ['row' => $row['_row'] ?? 'variants', 'message' => 'product_code is required.'];

                continue;
            }
            $variantsByProduct[$code][] = $row;
        }

        $productCodes = [];
        foreach ($productRows as $row) {
            $code = strtoupper(trim((string) ($row['product_code'] ?? '')));
            if ($code !== '') {
                $productCodes[$code] = true;
            }
        }

        foreach ($variantsByProduct as $code => $_) {
            if (! isset($productCodes[$code])) {
                $errors[] = [
                    'row' => "variants:{$code}",
                    'message' => "product_code \"{$code}\" is not on the products sheet.",
                ];
            }
        }

        DB::transaction(function () use ($productRows, $variantsByProduct, &$created, &$updated, &$skipped, &$errors) {
            foreach ($productRows as $row) {
                $rowRef = (string) ($row['_row'] ?? 'products');
                $name = trim((string) ($row['name'] ?? ''));
                $code = strtoupper(trim((string) ($row['product_code'] ?? '')));

                if ($name === '') {
                    $skipped++;
                    $errors[] = ['row' => $rowRef, 'message' => 'name is required.'];

                    continue;
                }

                if ($code === '') {
                    $code = Product::resolveCode(null);
                }

                $type = strtolower(trim((string) ($row['type'] ?? 'single')));
                $type = $type === 'variant' ? 'variant' : 'single';

                $purchaseUnit = $this->resolveUnit($row['purchase_unit'] ?? 'pcs');
                $saleUnit = $this->resolveUnit($row['sale_unit'] ?? 'pcs') ?? $purchaseUnit;
                if (! $purchaseUnit || ! $saleUnit) {
                    $skipped++;
                    $errors[] = ['row' => $rowRef, 'message' => 'Units not found. Create units before importing.'];

                    continue;
                }

                $brandId = $this->resolveBrandId($row['brand'] ?? '');
                $categoryId = $this->resolveCategoryId($row['category'] ?? '');
                $taxId = null;
                $taxCode = trim((string) ($row['tax'] ?? ''));
                if ($taxCode !== '') {
                    $taxId = Tax::query()->whereRaw('UPPER(code) = ?', [strtoupper($taxCode)])->value('id');
                    if (! $taxId) {
                        $skipped++;
                        $errors[] = ['row' => $rowRef, 'message' => "Tax code \"{$taxCode}\" not found."];

                        continue;
                    }
                }

                $variationId = null;
                if ($type === 'variant') {
                    $variationCode = trim((string) ($row['variation_code'] ?? ''));
                    if ($variationCode === '') {
                        $skipped++;
                        $errors[] = ['row' => $rowRef, 'message' => 'variation_code is required for type=variant.'];

                        continue;
                    }
                    $variation = Variation::query()
                        ->whereRaw('UPPER(code) = ?', [strtoupper($variationCode)])
                        ->first();
                    if (! $variation) {
                        $skipped++;
                        $errors[] = ['row' => $rowRef, 'message' => "Variation code \"{$variationCode}\" not found."];

                        continue;
                    }
                    $variationId = $variation->id;
                }

                $productAttrs = [
                    'name' => $name,
                    'type' => $type,
                    'short_code' => $code,
                    'barcode' => ($row['barcode'] ?? '') !== '' ? $row['barcode'] : null,
                    'brand_id' => $brandId,
                    'category_id' => $categoryId,
                    'variation_id' => $variationId,
                    'tax_id' => $taxId,
                    'purchase_unit_id' => $purchaseUnit->id,
                    'sale_unit_id' => $saleUnit->id,
                    'conversion_rate' => (float) ($row['conversion_rate'] ?? 1) ?: 1,
                    'sale_price' => (float) ($row['sale_price'] ?? 0),
                    'cost_per_unit' => (float) ($row['purchase_price'] ?? 0),
                    'min_qty_alert' => ($row['min_qty_alert'] ?? '') !== '' ? (float) $row['min_qty_alert'] : null,
                    'track_stock' => $this->toBool($row['track_stock'] ?? true, true),
                    'is_active' => $this->toBool($row['is_active'] ?? true, true),
                    'notes' => ($row['notes'] ?? '') !== '' ? $row['notes'] : null,
                ];

                $product = Product::query()->whereRaw('UPPER(short_code) = ?', [$code])->first();
                $isNew = ! $product;

                if ($product) {
                    $product->update($productAttrs);
                    $updated++;
                } else {
                    $product = Product::query()->create($productAttrs);
                    $created++;
                }

                $childRows = $variantsByProduct[$code] ?? [];

                if ($type === 'single') {
                    $this->syncSingleVariant($product, $row, $purchaseUnit->id, $saleUnit->id);
                    continue;
                }

                if ($childRows === []) {
                    $errors[] = [
                        'row' => $rowRef,
                        'message' => "No variants sheet rows for product_code \"{$code}\".",
                    ];
                    if ($isNew) {
                        // keep product; user can fix in next import
                    }

                    continue;
                }

                $this->syncVariantRows($product, $childRows, $variationId, $errors, $skipped);
            }
        });

        return compact('created', 'updated', 'skipped', 'errors');
    }

    protected function syncSingleVariant(Product $product, array $row, int $purchaseUnitId, int $saleUnitId): void
    {
        $attrs = [
            'variation_option_id' => null,
            'name' => null,
            'short_code' => $product->short_code,
            'barcode' => $product->barcode,
            'purchase_unit_id' => $purchaseUnitId,
            'sale_unit_id' => $saleUnitId,
            'conversion_rate' => $product->conversion_rate,
            'sale_price' => $product->sale_price,
            'cost_per_unit' => $product->cost_per_unit,
            'track_serial' => $this->toBool($row['track_serial'] ?? false, false),
            'is_active' => $product->is_active,
            'sort_order' => 0,
        ];

        $variant = $product->variants()->orderBy('id')->first();
        if ($variant) {
            $variant->update($attrs);
            $keepId = $variant->id;
        } else {
            $keepId = $product->variants()->create($attrs)->id;
        }

        $product->variants()->where('id', '!=', $keepId)->update(['is_active' => false]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{row:string, message:string}>  $errors
     */
    protected function syncVariantRows(
        Product $product,
        array $rows,
        ?int $variationId,
        array &$errors,
        int &$skipped,
    ): void {
        $keepIds = [];
        $reserved = [];

        foreach (array_values($rows) as $index => $row) {
            $rowRef = (string) ($row['_row'] ?? 'variants');
            $option = $this->resolveOption($variationId, $row['option_code'] ?? '', $row['option_name'] ?? '');
            if (! $option) {
                $skipped++;
                $errors[] = [
                    'row' => $rowRef,
                    'message' => 'option_code or option_name must match an option on the product variation.',
                ];

                continue;
            }

            $purchaseUnit = $this->resolveUnit($row['purchase_unit'] ?? '') ?? $product->purchaseUnit;
            $saleUnit = $this->resolveUnit($row['sale_unit'] ?? '') ?? $product->saleUnit ?? $purchaseUnit;
            if (! $purchaseUnit || ! $saleUnit) {
                $skipped++;
                $errors[] = ['row' => $rowRef, 'message' => 'Units not found for this variant row.'];

                continue;
            }

            $short = strtoupper(trim((string) ($row['short_code'] ?? '')));
            if ($short === '') {
                $short = Product::nextAutoCode($reserved);
            }
            $reserved[] = $short;

            $attrs = [
                'variation_option_id' => $option->id,
                'name' => $option->name,
                'short_code' => $short,
                'barcode' => ($row['barcode'] ?? '') !== '' ? $row['barcode'] : null,
                'purchase_unit_id' => $purchaseUnit->id,
                'sale_unit_id' => $saleUnit->id,
                'conversion_rate' => (float) ($row['conversion_rate'] ?? $product->conversion_rate) ?: 1,
                'sale_price' => (float) ($row['sale_price'] ?? $product->sale_price),
                'cost_per_unit' => (float) ($row['purchase_price'] ?? $product->cost_per_unit),
                'track_serial' => $this->toBool($row['track_serial'] ?? false, false),
                'is_active' => $this->toBool($row['is_active'] ?? true, true),
                'sort_order' => $index,
            ];

            $variant = $product->variants()
                ->where('variation_option_id', $option->id)
                ->first()
                ?? ProductVariant::query()->whereRaw('UPPER(short_code) = ?', [$short])->first();

            if ($variant && (int) $variant->product_id !== (int) $product->id) {
                $skipped++;
                $errors[] = ['row' => $rowRef, 'message' => "short_code \"{$short}\" belongs to another product."];

                continue;
            }

            if ($variant) {
                $variant->update($attrs);
            } else {
                $variant = $product->variants()->create($attrs);
            }

            $keepIds[] = $variant->id;
        }

        if ($keepIds !== []) {
            $product->variants()->whereNotIn('id', $keepIds)->update(['is_active' => false]);

            $first = $product->variants()->whereIn('id', $keepIds)->orderBy('sort_order')->first();
            if ($first) {
                $product->update([
                    'barcode' => $first->barcode,
                    'purchase_unit_id' => $first->purchase_unit_id,
                    'sale_unit_id' => $first->sale_unit_id,
                    'conversion_rate' => $first->conversion_rate,
                    'sale_price' => $first->sale_price,
                    'cost_per_unit' => $first->cost_per_unit,
                ]);
            }
        }
    }

    protected function resolveOption(?int $variationId, string $optionCode, string $optionName): ?VariationOption
    {
        if (! $variationId) {
            return null;
        }

        $optionCode = trim($optionCode);
        $optionName = trim($optionName);

        if ($optionCode !== '') {
            $byCode = VariationOption::query()
                ->where('variation_id', $variationId)
                ->whereRaw('UPPER(code) = ?', [strtoupper($optionCode)])
                ->first();
            if ($byCode) {
                return $byCode;
            }
        }

        if ($optionName !== '') {
            return VariationOption::query()
                ->where('variation_id', $variationId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($optionName)])
                ->first();
        }

        return null;
    }

    protected function resolveUnit(string $code): ?Unit
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            return null;
        }

        return Unit::query()->whereRaw('LOWER(code) = ?', [$code])->first();
    }

    protected function resolveBrandId(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $brand = Brand::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($brand) {
            return $brand->id;
        }

        return Brand::query()->create([
            'name' => $name,
            'code' => Brand::resolveCode(null),
            'is_active' => true,
        ])->id;
    }

    protected function resolveCategoryId(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $category = Category::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($category) {
            return $category->id;
        }

        return Category::query()->create([
            'name' => $name,
            'code' => Category::resolveCode(null),
            'is_active' => true,
        ])->id;
    }

    protected function toBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    protected function normalizeSheetTitle(string $title): string
    {
        $title = strtolower(trim($title));
        $title = preg_replace('/\s+/', ' ', $title) ?? $title;

        return $title;
    }

    protected function resolveSheetKey(string $title, int $index): ?string
    {
        foreach (self::SHEET_ALIASES as $key => $aliases) {
            if (in_array($title, $aliases, true)) {
                return $key;
            }
        }

        return match ($index) {
            0 => 'products',
            1 => 'variants',
            default => null,
        };
    }

    /**
     * @param  list<mixed>  $header
     * @return list<string>
     */
    protected function normalizeHeader(array $header): array
    {
        return array_map(function ($h) {
            $value = strtolower(trim((string) $h));
            $value = preg_replace('/^\xef\xbb\xbf/', '', $value) ?? $value;
            $value = str_replace([' ', '-'], '_', $value);

            return $value;
        }, $header);
    }

    /**
     * @param  list<mixed>  $row
     */
    protected function rowIsEmpty(array $row): bool
    {
        return count(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== '')) === 0;
    }

    protected function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename): BinaryFileResponse
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'products_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()
            ->download($tempPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    public static function fileRules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:5120',
                'extensions:xlsx,xls',
            ],
        ];
    }
}
