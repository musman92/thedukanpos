<?php

namespace App\Http\Requests\Admin;

use App\Services\AttendanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
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
            'attendance_date' => ['required', 'date'],
            'clock_in' => ['nullable', 'string', 'max:32'],
            'clock_out' => ['nullable', 'string', 'max:32'],
            'status' => ['required', 'string', Rule::in(AttendanceService::STATUSES)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array{
     *   user_id:int,
     *   attendance_date:string,
     *   clock_in:string|null,
     *   clock_out:string|null,
     *   status:string,
     *   notes:string|null
     * }
     */
    public function payload(): array
    {
        return [
            'user_id' => (int) $this->input('user_id'),
            'attendance_date' => (string) $this->input('attendance_date'),
            'clock_in' => $this->input('clock_in'),
            'clock_out' => $this->input('clock_out'),
            'status' => (string) $this->input('status'),
            'notes' => $this->input('notes'),
        ];
    }
}
