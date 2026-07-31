<?php

namespace App\Http\Requests\Admin;

use App\Services\StockDamageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockDamageRequest extends FormRequest
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
        return [
            'reason' => ['required', 'string', Rule::in(array_keys(StockDamageService::REASONS))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ];
    }

    /**
     * @return array{
     *   reason:string,
     *   notes:?string,
     *   items: list<array{variant_id:int, quantity:float}>
     * }
     */
    public function payload(): array
    {
        return [
            'reason' => (string) $this->input('reason'),
            'notes' => $this->filled('notes') ? (string) $this->input('notes') : null,
            'items' => collect($this->input('items', []))
                ->map(fn ($row) => [
                    'variant_id' => (int) ($row['variant_id'] ?? 0),
                    'quantity' => (float) ($row['quantity'] ?? 0),
                ])
                ->values()
                ->all(),
        ];
    }
}
