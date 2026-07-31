<?php

namespace App\Http\Requests\Admin;

use App\Models\Brand;
use App\Services\ImageUploadService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StoreBrandRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,webp,gif',
                'max:'.ImageUploadService::MAX_UPLOAD_KB,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'image.max' => 'Image must be at most '.(ImageUploadService::MAX_UPLOAD_KB / 1024).' MB before compression.',
            'image.image' => 'Please upload a valid image file.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $name = trim((string) $this->input('name', ''));
            if ($name !== '') {
                $nameTaken = Brand::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->exists();

                if ($nameTaken) {
                    $validator->errors()->add('name', 'This brand name is already taken.');
                }
            }

            $code = trim((string) $this->input('code', ''));
            if ($code === '') {
                return;
            }

            $exists = Brand::query()
                ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
                ->exists();

            if ($exists) {
                $validator->errors()->add('code', 'This brand code is already taken.');
            }
        });
    }

    /**
     * @return array{name: string, code: string|null, is_active: bool, image: UploadedFile|null}
     */
    public function payload(): array
    {
        return [
            'name' => trim((string) $this->input('name')),
            'code' => $this->input('code'),
            'is_active' => $this->boolean('is_active', true),
            'image' => $this->file('image'),
        ];
    }
}
