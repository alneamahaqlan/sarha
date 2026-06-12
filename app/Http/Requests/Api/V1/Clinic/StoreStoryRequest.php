<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class StoreStoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        return [
            // Relative storage path returned by the /uploads endpoint.
            'image'      => ['required', 'string', 'max:2048'],
            'caption'    => ['nullable', 'string', 'max:255'],
            'ends_at'    => ['nullable', 'date'],
            'is_active'  => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
