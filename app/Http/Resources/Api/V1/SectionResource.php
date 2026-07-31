<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Section */
class SectionResource extends JsonResource
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
            'is_active' => (bool) $this->is_active,
            'racks_count' => (int) ($this->racks_count ?? $this->racks?->count() ?? 0),
            'racks' => $this->whenLoaded('racks', fn () => $this->racks->map(fn ($rack) => [
                'id' => $rack->id,
                'name' => $rack->name,
                'code' => $rack->code,
                'is_active' => (bool) $rack->is_active,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
