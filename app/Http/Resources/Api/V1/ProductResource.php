<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type ?: 'single',
            'short_code' => $this->short_code,
            'barcode' => $this->barcode,
            'sku' => $this->sku,
            'image' => $this->image,
            'image_url' => $this->image
                ? route('api.v1.media.show', ['path' => $this->image])
                : null,
            'brand_id' => $this->brand_id,
            'category_id' => $this->category_id,
            'variation_id' => $this->variation_id,
            'tax_id' => $this->tax_id,
            'purchase_unit_id' => $this->purchase_unit_id,
            'sale_unit_id' => $this->sale_unit_id,
            'conversion_rate' => $this->conversion_rate !== null ? (float) $this->conversion_rate : null,
            'sale_price' => $this->sale_price !== null ? (float) $this->sale_price : null,
            'cost_per_unit' => $this->cost_per_unit !== null ? (float) $this->cost_per_unit : null,
            'min_qty_alert' => $this->min_qty_alert !== null ? (float) $this->min_qty_alert : null,
            'track_stock' => (bool) $this->track_stock,
            'is_active' => (bool) $this->is_active,
            'notes' => $this->notes,
            'variants_count' => (int) ($this->variants_count ?? $this->variants?->count() ?? 0),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand ? [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
                'code' => $this->brand->code,
            ] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'code' => $this->category->code,
            ] : null),
            'tax' => $this->whenLoaded('tax', fn () => $this->tax ? [
                'id' => $this->tax->id,
                'name' => $this->tax->name,
                'code' => $this->tax->code,
                'rate' => (float) $this->tax->rate,
            ] : null),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(function ($variant) {
                $location = $variant->relationLoaded('locations')
                    ? $variant->locations->first()
                    : null;
                $stock = $variant->relationLoaded('stocks')
                    ? $variant->stocks->first()
                    : null;

                return [
                    'id' => $variant->id,
                    'variation_option_id' => $variant->variation_option_id,
                    'name' => $variant->name,
                    'short_code' => $variant->short_code,
                    'barcode' => $variant->barcode,
                    'purchase_unit_id' => $variant->purchase_unit_id,
                    'sale_unit_id' => $variant->sale_unit_id,
                    'conversion_rate' => (float) $variant->conversion_rate,
                    'sale_price' => (float) $variant->sale_price,
                    'is_active' => (bool) $variant->is_active,
                    'track_serial' => (bool) $variant->track_serial,
                    'sort_order' => (int) $variant->sort_order,
                    'purchase_unit' => $variant->relationLoaded('purchaseUnit') && $variant->purchaseUnit ? [
                        'id' => $variant->purchaseUnit->id,
                        'name' => $variant->purchaseUnit->name,
                        'code' => $variant->purchaseUnit->code,
                    ] : null,
                    'sale_unit' => $variant->relationLoaded('saleUnit') && $variant->saleUnit ? [
                        'id' => $variant->saleUnit->id,
                        'name' => $variant->saleUnit->name,
                        'code' => $variant->saleUnit->code,
                    ] : null,
                    'section_id' => $location?->section_id,
                    'rack_id' => $location?->rack_id,
                    'location' => $location ? [
                        'section_id' => $location->section_id,
                        'rack_id' => $location->rack_id,
                        'section' => $location->relationLoaded('section') && $location->section ? [
                            'id' => $location->section->id,
                            'name' => $location->section->name,
                            'code' => $location->section->code,
                        ] : null,
                        'rack' => $location->relationLoaded('rack') && $location->rack ? [
                            'id' => $location->rack->id,
                            'name' => $location->rack->name,
                            'code' => $location->rack->code,
                        ] : null,
                    ] : null,
                    'stock_quantity' => $stock ? (float) $stock->quantity : 0.0,
                ];
            })->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
