<?php

namespace App\Http\Requests\Admin;

use App\Models\LedgerTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
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
            'money_source_id' => ['nullable', 'integer', 'exists:money_sources,id'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'txn_date' => ['required', 'date'],
            'reference_type' => ['nullable', 'string', Rule::in(LedgerTransaction::REFERENCE_TYPES)],
            'reference_id' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array{
     *   account_id:int,
     *   money_source_id:int|null,
     *   direction:string,
     *   amount:float,
     *   txn_date:string,
     *   reference_type:string|null,
     *   reference_id:int|null,
     *   notes:string|null
     * }
     */
    public function payload(): array
    {
        $referenceType = $this->filled('reference_type')
            ? (string) $this->input('reference_type')
            : null;

        return [
            'account_id' => (int) $this->input('account_id'),
            'money_source_id' => $this->filled('money_source_id') ? (int) $this->input('money_source_id') : null,
            'direction' => (string) $this->input('direction'),
            'amount' => (float) $this->input('amount'),
            'txn_date' => (string) $this->input('txn_date'),
            'reference_type' => $referenceType,
            'reference_id' => $this->filled('reference_id') ? (int) $this->input('reference_id') : null,
            'notes' => $this->input('notes'),
        ];
    }
}
