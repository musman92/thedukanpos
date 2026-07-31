<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Unit */
class UnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $usage = $this->usage_count ?? (
            (int) ($this->products_as_purchase_count ?? 0)
            + (int) ($this->products_as_sale_count ?? 0)
            + (int) ($this->variants_as_purchase_count ?? 0)
            + (int) ($this->variants_as_sale_count ?? 0)
        );

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'is_active' => (bool) $this->is_active,
            'usage_count' => (int) $usage,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
