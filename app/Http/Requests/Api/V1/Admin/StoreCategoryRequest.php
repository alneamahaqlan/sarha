<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('create', \App\Models\Category::class);
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:255'],
            'name_en'    => ['nullable', 'string', 'max:255'],
            'slug'       => ['required', 'string', 'max:255', 'unique:categories,slug'],
            'emoji'      => ['nullable', 'string', 'max:5'],
            'icon'       => ['nullable', 'string', 'max:100'],
            'is_active'  => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
