<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
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
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'number' => ['nullable', 'string', 'max:50', Rule::unique('purchases', 'number')],
            'purchase_date' => ['required', 'date'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'money_source_id' => ['nullable', 'integer', 'exists:money_sources,id'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.bonus_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.bonus_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.expiry_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $number = trim((string) ($this->input('number') ?? ''));

        return [
            'supplier_id' => $this->input('supplier_id'),
            'number' => $number !== '' ? $number : null,
            'purchase_date' => (string) $this->input('purchase_date'),
            'tax_total' => (float) ($this->input('tax_total') ?? 0),
            'discount_total' => (float) ($this->input('discount_total') ?? 0),
            'notes' => $this->input('notes'),
            'money_source_id' => $this->input('money_source_id'),
            'paid_amount' => (float) ($this->input('paid_amount') ?? 0),
            'items' => collect($this->input('items', []))->map(fn ($row) => [
                'variant_id' => (int) $row['variant_id'],
                'unit_id' => (int) $row['unit_id'],
                'quantity' => (float) $row['quantity'],
                'bonus_quantity' => (float) ($row['bonus_quantity'] ?? 0),
                'bonus_unit_id' => $row['bonus_unit_id'] ?? null,
                'unit_price' => (float) $row['unit_price'],
                'expiry_date' => ! empty($row['expiry_date']) ? (string) $row['expiry_date'] : null,
            ])->all(),
        ];
    }
}
