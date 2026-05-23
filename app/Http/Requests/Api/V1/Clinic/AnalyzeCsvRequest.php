<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        // Mirrors ImportServices Filament FileUpload: csv only, max 2MB.
        return [
            'file' => ['required', 'file', 'mimetypes:text/csv,text/plain,application/csv', 'max:2048'],
        ];
    }
}
