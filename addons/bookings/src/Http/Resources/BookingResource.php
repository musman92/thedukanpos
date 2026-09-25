<?php

namespace Addons\Bookings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'product_id' => $this->product_id,
            'customer_id' => $this->customer_id,
            'sale_id' => $this->sale_id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'address' => $this->address,
            'notes' => $this->notes,
            'service_name' => $this->service_name,
            'price_mode' => $this->price_mode,
            'price' => $this->price !== null ? (float) $this->price : null,
            'duration_minutes' => $this->duration_minutes,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'status' => $this->status,
            'source' => $this->source,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
