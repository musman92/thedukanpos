<?php

namespace App\Http\Requests\Admin;

use App\Services\AttendanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LiveAttendanceActionRequest extends FormRequest
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
            'action' => ['required', 'string', Rule::in(AttendanceService::LIVE_ACTIONS)],
        ];
    }
}
