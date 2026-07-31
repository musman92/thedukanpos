<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
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
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'money_source_id' => ['required', 'integer', 'exists:money_sources,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array{
     *   account_id:int,
     *   money_source_id:int,
     *   amount:float,
     *   expense_date:string,
     *   notes:string|null
     * }
     */
    public function payload(): array
    {
        return [
            'account_id' => (int) $this->input('account_id'),
            'money_source_id' => (int) $this->input('money_source_id'),
            'amount' => (float) $this->input('amount'),
            'expense_date' => (string) $this->input('expense_date'),
            'notes' => $this->input('notes'),
        ];
    }
}
