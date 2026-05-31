<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'min:3', 'max:20000'],
        ];
    }
}
