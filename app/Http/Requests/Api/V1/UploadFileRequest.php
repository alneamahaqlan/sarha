<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends FormRequest
{
    private const ALLOWED_DIRECTORIES = [
        'clinics/logos',
        'clinics/gallery',
        'articles',
    ];

    public function authorize(): bool
    {
        // Any authenticated admin or clinic can upload.
        return $this->user('admin') !== null || $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        return [
            'file'      => ['required', 'file', 'image', 'max:4096'],
            'directory' => ['required', 'in:' . implode(',', self::ALLOWED_DIRECTORIES)],
        ];
    }
}
