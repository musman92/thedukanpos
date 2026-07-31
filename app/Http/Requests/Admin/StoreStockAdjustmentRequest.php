<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockAdjustmentRequest extends FormRequest
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
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'mode' => ['required', 'in:change,exact'],
            'unit' => ['nullable', 'in:sale,purchase'],
            'quantity' => ['required', 'numeric'],
            'notes' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array{
     *   variant_id:int,
     *   mode:string,
     *   unit:string,
     *   quantity:float,
     *   notes:string
     * }
     */
    public function payload(): array
    {
        return [
            'variant_id' => (int) $this->input('variant_id'),
            'mode' => $this->input('mode') === 'exact' ? 'exact' : 'change',
            'unit' => $this->input('unit') === 'purchase' ? 'purchase' : 'sale',
            'quantity' => (float) $this->input('quantity'),
            'notes' => (string) $this->input('notes'),
        ];
    }
}
