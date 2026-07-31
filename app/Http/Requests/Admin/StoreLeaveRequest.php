<?php

namespace App\Http\Requests\Admin;

use App\Services\LeaveService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveRequest extends FormRequest
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
            'leave_type' => ['required', 'string', Rule::in(LeaveService::TYPES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array{
     *   user_id:int,
     *   leave_type:string,
     *   start_date:string,
     *   end_date:string,
     *   reason:string|null
     * }
     */
    public function payload(): array
    {
        return [
            'user_id' => (int) $this->input('user_id'),
            'leave_type' => (string) $this->input('leave_type'),
            'start_date' => (string) $this->input('start_date'),
            'end_date' => (string) $this->input('end_date'),
            'reason' => $this->input('reason'),
        ];
    }
}
