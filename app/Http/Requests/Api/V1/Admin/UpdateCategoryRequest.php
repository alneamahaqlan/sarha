<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('update', $this->route('category'));
    }

    public function rules(): array
    {
        $id = $this->route('category')?->id;

        return [
            'name'       => ['sometimes', 'required', 'string', 'max:255'],
            'name_en'    => ['nullable', 'string', 'max:255'],
            'slug'       => ['sometimes', 'required', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($id)],
            'emoji'      => ['nullable', 'string', 'max:5'],
            'icon'       => ['nullable', 'string', 'max:255'],
            'is_active'  => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
