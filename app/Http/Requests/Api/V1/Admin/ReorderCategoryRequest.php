<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReorderCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('reorder', \App\Models\Category::class);
    }

    public function rules(): array
    {
        return [
            'order'           => ['required', 'array', 'min:1'],
            'order.*.id'      => ['required', 'integer', 'exists:categories,id'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
