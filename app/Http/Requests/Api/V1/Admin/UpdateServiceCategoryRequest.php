<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('update', $this->route('service_category'));
    }

    public function rules(): array
    {
        $id = $this->route('service_category')?->id;

        return [
            'name'        => ['sometimes', 'required', 'string', 'max:255'],
            'name_en'     => ['nullable', 'string', 'max:255'],
            'slug'        => ['sometimes', 'required', 'string', 'max:255',
                Rule::unique('service_categories', 'slug')->ignore($id),
            ],
            'emoji'       => ['nullable', 'string', 'max:8'],
            'icon'        => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['nullable', 'boolean'],
            'sort_order'  => ['nullable', 'integer'],
        ];
    }
}
