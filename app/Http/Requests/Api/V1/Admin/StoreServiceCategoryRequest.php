<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\ServiceCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreServiceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('create', ServiceCategory::class);
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'name_en'     => ['nullable', 'string', 'max:255'],
            'slug'        => ['required', 'string', 'max:255', 'unique:service_categories,slug'],
            'emoji'       => ['nullable', 'string', 'max:8'],
            'icon'        => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['nullable', 'boolean'],
            'sort_order'  => ['nullable', 'integer'],
        ];
    }
}
