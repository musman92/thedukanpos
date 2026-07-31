<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewLeaveRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(['approved', 'rejected'])],
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array{status:string, review_notes:string|null}
     */
    public function payload(): array
    {
        return [
            'status' => (string) $this->input('status'),
            'review_notes' => $this->input('review_notes'),
        ];
    }
}
