<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class ImportServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        return [
            'headers'        => ['required', 'array', 'min:1'],
            'headers.*'      => ['string'],
            'rows'           => ['required', 'array', 'min:1'],
            'rows.*'         => ['array'],
            'mapping'        => ['required', 'array', 'min:1'],
            // Mapping is { headerIndex: modelField } — modelField restricted to allowed.
            'mapping.*'      => ['nullable', 'in:name,description,price'],
        ];
    }
}
