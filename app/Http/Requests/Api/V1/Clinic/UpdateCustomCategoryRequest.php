<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null
            && $this->user('clinic')->can('update', $this->route('customCategory'));
    }

    public function rules(): array
    {
        return [
            'name'       => ['sometimes', 'required', 'string', 'max:255'],
            'emoji'      => ['nullable', 'string', 'max:5'],
            'is_active'  => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
