<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Category */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'parent_id' => $this->parent_id,
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? [
                'id' => $this->parent->id,
                'name' => $this->parent->name,
                'code' => $this->parent->code,
            ] : null),
            'default_tax_id' => $this->default_tax_id,
            'default_tax' => $this->whenLoaded('defaultTax', fn () => $this->defaultTax ? [
                'id' => $this->defaultTax->id,
                'name' => $this->defaultTax->name,
                'code' => $this->defaultTax->code,
                'rate' => $this->defaultTax->rate,
            ] : null),
            'is_active' => (bool) $this->is_active,
            'products_count' => (int) ($this->products_count ?? 0),
            'children_count' => (int) ($this->children_count ?? 0),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
