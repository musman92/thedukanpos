<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariationOption;
use App\Services\ImageUploadService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');
        $type = $product->type ?: 'single';

        $rules = [
            'type' => ['sometimes', Rule::in(['single', 'variant'])],
            'name' => ['required', 'string', 'max:255'],
            'short_code' => ['nullable', 'string', 'max:50'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'variation_id' => ['nullable', 'exists:variations,id'],
            'tax_id' => ['nullable', 'exists:taxes,id'],
            'min_qty_alert' => ['nullable', 'numeric', 'min:0'],
            'track_stock' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'remove_image' => ['sometimes', 'boolean'],
            'image' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,webp,gif',
                'max:'.ImageUploadService::MAX_UPLOAD_KB,
            ],
        ];

        if ($type === 'single') {
            return [
                ...$rules,
                'barcode' => ['nullable', 'string', 'max:100'],
                'purchase_unit_id' => ['required', 'exists:units,id'],
                'sale_unit_id' => ['required', 'exists:units,id'],
                'conversion_rate' => ['required', 'numeric', 'min:0.0001'],
                'sale_price' => ['required', 'numeric', 'min:0'],
                'cost_per_unit' => ['nullable', 'numeric', 'min:0'],
                'section_id' => ['nullable', 'exists:sections,id'],
                'rack_id' => ['nullable', 'exists:racks,id'],
                'track_serial' => ['sometimes', 'boolean'],
            ];
        }

        return [
            ...$rules,
            'variation_id' => ['required', 'exists:variations,id'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.variation_option_id' => ['required', 'exists:variation_options,id'],
            'variants.*.name' => ['nullable', 'string', 'max:255'],
            'variants.*.short_code' => ['nullable', 'string', 'max:50'],
            'variants.*.barcode' => ['nullable', 'string', 'max:100'],
            'variants.*.purchase_unit_id' => ['required', 'exists:units,id'],
            'variants.*.sale_unit_id' => ['required', 'exists:units,id'],
            'variants.*.conversion_rate' => ['required', 'numeric', 'min:0.0001'],
            'variants.*.sale_price' => ['required', 'numeric', 'min:0'],
            'variants.*.cost_per_unit' => ['nullable', 'numeric', 'min:0'],
            'variants.*.section_id' => ['nullable', 'exists:sections,id'],
            'variants.*.rack_id' => ['nullable', 'exists:racks,id'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
            'variants.*.track_serial' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Product $product */
            $product = $this->route('product');
            $type = $product->type ?: 'single';

            $code = trim((string) $this->input('short_code', ''));
            if ($code !== '') {
                $taken = Product::query()
                    ->whereRaw('UPPER(short_code) = ?', [strtoupper($code)])
                    ->where('id', '!=', $product->id)
                    ->exists();

                $variantTaken = ProductVariant::query()
                    ->whereRaw('UPPER(short_code) = ?', [strtoupper($code)])
                    ->where('product_id', '!=', $product->id)
                    ->exists();

                if ($taken || $variantTaken) {
                    $validator->errors()->add('short_code', 'This product code is already taken.');
                }
            }

            if ($type === 'single') {
                $barcode = trim((string) $this->input('barcode', ''));
                if ($barcode !== '') {
                    $ignoreVariantId = $product->variants()->orderBy('id')->value('id');
                    $taken = Product::query()
                        ->where('barcode', $barcode)
                        ->where('id', '!=', $product->id)
                        ->exists()
                        || ProductVariant::query()
                            ->where('barcode', $barcode)
                            ->when($ignoreVariantId, fn ($q) => $q->where('id', '!=', $ignoreVariantId))
                            ->exists();
                    if ($taken) {
                        $validator->errors()->add('barcode', 'This barcode is already taken.');
                    }
                }

                return;
            }

            $variationId = (int) ($this->input('variation_id') ?: $product->variation_id);
            $variants = $this->input('variants', []);
            if (! is_array($variants)) {
                return;
            }

            if ($variationId > 0) {
                foreach (array_values($variants) as $index => $variant) {
                    $optionId = (int) ($variant['variation_option_id'] ?? 0);
                    if ($optionId < 1) {
                        continue;
                    }
                    $belongs = VariationOption::query()
                        ->whereKey($optionId)
                        ->where('variation_id', $variationId)
                        ->exists();
                    if (! $belongs) {
                        $validator->errors()->add(
                            "variants.{$index}.variation_option_id",
                            'Option does not belong to the selected variation.',
                        );
                    }
                }
            }

            $shortCodes = collect($variants)
                ->pluck('short_code')
                ->map(fn ($c) => strtoupper(trim((string) $c)))
                ->filter();

            if ($shortCodes->count() !== $shortCodes->unique()->count()) {
                $validator->errors()->add('variants', 'Variant short codes must be unique within this product.');
            }

            foreach (array_values($variants) as $index => $variant) {
                if (! is_array($variant)) {
                    continue;
                }

                $ignoreId = isset($variant['id']) ? (int) $variant['id'] : null;

                if ($ignoreId) {
                    $belongs = ProductVariant::query()
                        ->where('product_id', $product->id)
                        ->whereKey($ignoreId)
                        ->exists();
                    if (! $belongs) {
                        $validator->errors()->add("variants.{$index}.id", 'Invalid variant for this product.');
                    }
                }

                $short = strtoupper(trim((string) ($variant['short_code'] ?? '')));
                if ($short !== '') {
                    $shortTaken = ProductVariant::query()
                        ->whereRaw('UPPER(short_code) = ?', [$short])
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->exists();
                    if ($shortTaken) {
                        $validator->errors()->add("variants.{$index}.short_code", 'This short code is already taken.');
                    }
                }

                $vBarcode = trim((string) ($variant['barcode'] ?? ''));
                if ($vBarcode === '') {
                    continue;
                }

                $barcodeTaken = ProductVariant::query()
                    ->where('barcode', $vBarcode)
                    ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                    ->exists();
                if ($barcodeTaken) {
                    $validator->errors()->add("variants.{$index}.barcode", 'This barcode is already taken.');
                }
            }
        });
    }

    /**
     * @return array{product: array<string, mixed>, variants: list<array<string, mixed>>, branch_id: int|null}
     */
    public function payload(): array
    {
        /** @var Product $product */
        $product = $this->route('product');
        $type = $product->type ?: 'single';

        $data = [
            'name' => trim((string) $this->input('name')),
            'type' => $type,
            'short_code' => $this->input('short_code'),
            'brand_id' => $this->input('brand_id') ?: null,
            'category_id' => $this->input('category_id') ?: null,
            'variation_id' => $type === 'variant'
                ? ($this->input('variation_id') ?: $product->variation_id)
                : null,
            'tax_id' => $this->input('tax_id') ?: null,
            'min_qty_alert' => $this->input('min_qty_alert'),
            'track_stock' => $this->boolean('track_stock', true),
            'is_active' => $this->boolean('is_active', true),
            'notes' => $this->input('notes') ?: null,
            'sku' => null,
        ];

        if ($type === 'single') {
            $data['barcode'] = $this->input('barcode') ?: null;
            $data['purchase_unit_id'] = $this->input('purchase_unit_id');
            $data['sale_unit_id'] = $this->input('sale_unit_id');
            $data['conversion_rate'] = $this->input('conversion_rate');
            $data['sale_price'] = $this->input('sale_price');
            $data['cost_per_unit'] = $this->input('cost_per_unit', 0) ?: 0;
            $data['section_id'] = $this->input('section_id') ?: null;
            $data['rack_id'] = $this->input('rack_id') ?: null;

            $existingVariantId = $product->variants()->orderBy('id')->value('id');

            $variants = [[
                'id' => $existingVariantId,
                'barcode' => $data['barcode'],
                'purchase_unit_id' => $data['purchase_unit_id'],
                'sale_unit_id' => $data['sale_unit_id'],
                'conversion_rate' => $data['conversion_rate'],
                'sale_price' => $data['sale_price'],
                'cost_per_unit' => $data['cost_per_unit'],
                'section_id' => $data['section_id'],
                'rack_id' => $data['rack_id'],
                'track_serial' => $this->boolean('track_serial', false),
                'is_active' => $data['is_active'],
            ]];
        } else {
            $variants = array_values($this->input('variants', []));
            foreach ($variants as $i => $row) {
                $variants[$i]['cost_per_unit'] = $row['cost_per_unit'] ?? 0;
            }
            $first = $variants[0];
            $data['barcode'] = ($first['barcode'] ?? null) ?: null;
            $data['purchase_unit_id'] = $first['purchase_unit_id'];
            $data['sale_unit_id'] = $first['sale_unit_id'];
            $data['conversion_rate'] = $first['conversion_rate'];
            $data['sale_price'] = $first['sale_price'];
            $data['cost_per_unit'] = $first['cost_per_unit'] ?? 0;
        }

        return [
            'product' => $data,
            'variants' => $variants,
            'branch_id' => $this->filled('branch_id') ? (int) $this->input('branch_id') : null,
            'image' => $this->file('image'),
            'remove_image' => $this->boolean('remove_image'),
        ];
    }
}
