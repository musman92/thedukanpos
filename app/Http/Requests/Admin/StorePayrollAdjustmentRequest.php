<?php

namespace App\Http\Requests\Admin;

use App\Models\PayrollAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollAdjustmentRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', 'string', Rule::in(PayrollAdjustment::TYPES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'effective_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array{
     *   user_id:int,
     *   type:string,
     *   amount:float,
     *   effective_date:string,
     *   notes:string|null
     * }
     */
    public function payload(): array
    {
        return [
            'user_id' => (int) $this->input('user_id'),
            'type' => (string) $this->input('type'),
            'amount' => (float) $this->input('amount'),
            'effective_date' => (string) $this->input('effective_date'),
            'notes' => $this->input('notes'),
        ];
    }
}
