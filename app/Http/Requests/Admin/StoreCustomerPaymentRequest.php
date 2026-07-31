<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerPaymentRequest extends FormRequest
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
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'money_source_id' => ['required', 'integer', 'exists:money_sources,id'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'sale_amounts' => ['nullable', 'array'],
            'sale_amounts.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array{
     *   customer_id:int,
     *   money_source_id:int,
     *   payment_date:string,
     *   notes:string|null,
     *   total_amount:float|null,
     *   discount_amount:float,
     *   sale_amounts:array<int, float>
     * }
     */
    public function payload(): array
    {
        $saleAmounts = [];
        foreach ($this->input('sale_amounts', []) ?? [] as $saleId => $amount) {
            if ($amount === null || $amount === '') {
                continue;
            }
            $saleAmounts[(int) $saleId] = (float) $amount;
        }

        $total = $this->input('total_amount');
        $discount = $this->input('discount_amount');

        return [
            'customer_id' => (int) $this->input('customer_id'),
            'money_source_id' => (int) $this->input('money_source_id'),
            'payment_date' => (string) $this->input('payment_date'),
            'notes' => $this->input('notes'),
            'total_amount' => $total === null || $total === '' ? null : (float) $total,
            'discount_amount' => $discount === null || $discount === '' ? 0.0 : (float) $discount,
            'sale_amounts' => $saleAmounts,
        ];
    }
}
