<?php

namespace App\Http\Requests\Admin;

use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuotationRequest extends FormRequest
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
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'number' => ['nullable', 'string', 'max:50', Rule::unique('quotations', 'number')],
            'quote_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:quote_date'],
            'status' => ['nullable', 'string', Rule::in(Quotation::STATUSES)],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $number = trim((string) ($this->input('number') ?? ''));

        return [
            'customer_id' => $this->input('customer_id'),
            'number' => $number !== '' ? $number : null,
            'quote_date' => (string) $this->input('quote_date'),
            'valid_until' => ! empty($this->input('valid_until')) ? (string) $this->input('valid_until') : null,
            'status' => $this->input('status'),
            'discount_total' => (float) ($this->input('discount_total') ?? 0),
            'notes' => $this->input('notes'),
            'items' => collect($this->input('items', []))->map(fn ($row) => [
                'variant_id' => (int) $row['variant_id'],
                'unit_id' => (int) $row['unit_id'],
                'quantity' => (float) $row['quantity'],
                'unit_price' => (float) $row['unit_price'],
                'discount' => (float) ($row['discount'] ?? 0),
            ])->all(),
        ];
    }
}
