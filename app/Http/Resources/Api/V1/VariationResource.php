<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Variation */
class VariationResource extends JsonResource
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
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'options_count' => (int) ($this->options_count ?? $this->options?->count() ?? 0),
            'options' => $this->whenLoaded('options', fn () => $this->options->map(fn ($opt) => [
                'id' => $opt->id,
                'name' => $opt->name,
                'code' => $opt->code,
                'sort_order' => (int) $opt->sort_order,
                'is_active' => (bool) $opt->is_active,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
