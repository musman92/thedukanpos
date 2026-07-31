<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseReturnRequest extends FormRequest
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
            'return_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id' => ['required', 'integer', 'exists:purchase_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'return_date' => (string) $this->input('return_date'),
            'notes' => $this->input('notes'),
            'items' => collect($this->input('items', []))->map(fn ($row) => [
                'purchase_item_id' => (int) $row['purchase_item_id'],
                'quantity' => (float) ($row['quantity'] ?? 0),
            ])->all(),
        ];
    }
}
