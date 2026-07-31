<?php

namespace App\Http\Requests\Admin;

use App\Models\EmployeePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeePaymentRequest extends FormRequest
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
            'kind' => ['required', 'string', Rule::in(EmployeePayment::KINDS)],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'money_source_id' => ['required', 'integer', 'exists:money_sources,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payroll_item_id' => [
                Rule::requiredIf(fn () => $this->input('kind') === 'payroll'),
                'nullable',
                'integer',
                'exists:payroll_items,id',
            ],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payroll_item_id.required' => 'Select a finalized payslip to pay.',
        ];
    }

    /**
     * @return array{
     *   kind:string,
     *   user_id:int,
     *   money_source_id:int,
     *   amount:float,
     *   payroll_item_id:int|null,
     *   payment_date:string,
     *   notes:string|null
     * }
     */
    public function payload(): array
    {
        $kind = (string) $this->input('kind');

        return [
            'kind' => $kind,
            'user_id' => (int) $this->input('user_id'),
            'money_source_id' => (int) $this->input('money_source_id'),
            'amount' => (float) $this->input('amount'),
            'payroll_item_id' => $kind === 'payroll' && $this->filled('payroll_item_id')
                ? (int) $this->input('payroll_item_id')
                : null,
            'payment_date' => (string) $this->input('payment_date'),
            'notes' => $this->input('notes'),
        ];
    }
}
