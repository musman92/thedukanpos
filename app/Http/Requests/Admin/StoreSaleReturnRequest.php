<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleReturnRequest extends FormRequest
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
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
            'return_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', 'exists:sale_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'sale_id' => (int) $this->input('sale_id'),
            'return_date' => (string) $this->input('return_date'),
            'notes' => $this->input('notes'),
            'items' => collect($this->input('items', []))->map(fn ($row) => [
                'sale_item_id' => (int) $row['sale_item_id'],
                'quantity' => (float) ($row['quantity'] ?? 0),
            ])->all(),
        ];
    }
}
