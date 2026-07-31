<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierPaymentRequest extends FormRequest
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
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'money_source_id' => ['required', 'integer', 'exists:money_sources,id'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'purchase_amounts' => ['nullable', 'array'],
            'purchase_amounts.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array{
     *   supplier_id:int,
     *   money_source_id:int,
     *   payment_date:string,
     *   notes:string|null,
     *   total_amount:float|null,
     *   purchase_amounts:array<int, float>
     * }
     */
    public function payload(): array
    {
        $purchaseAmounts = [];
        foreach ($this->input('purchase_amounts', []) ?? [] as $purchaseId => $amount) {
            if ($amount === null || $amount === '') {
                continue;
            }
            $purchaseAmounts[(int) $purchaseId] = (float) $amount;
        }

        $total = $this->input('total_amount');

        return [
            'supplier_id' => (int) $this->input('supplier_id'),
            'money_source_id' => (int) $this->input('money_source_id'),
            'payment_date' => (string) $this->input('payment_date'),
            'notes' => $this->input('notes'),
            'total_amount' => $total === null || $total === '' ? null : (float) $total,
            'purchase_amounts' => $purchaseAmounts,
        ];
    }
}
