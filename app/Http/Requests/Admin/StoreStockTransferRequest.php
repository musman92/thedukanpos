<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
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
            'to_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ];
    }

    /**
     * @return array{
     *   to_branch_id:int,
     *   notes:?string,
     *   items: list<array{variant_id:int, quantity:float}>
     * }
     */
    public function payload(): array
    {
        return [
            'to_branch_id' => (int) $this->input('to_branch_id'),
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
