<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('update', $this->route('service'));
    }

    public function rules(): array
    {
        return [
            'clinic_id'    => ['sometimes', 'required', 'integer', 'exists:clinics,id'],
            'category_id'  => ['sometimes', 'required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'name'         => ['sometimes', 'required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'price'        => ['sometimes', 'required', 'numeric', 'min:0'],
            // Relative path returned by the /uploads endpoint (e.g. services/x.jpg).
            'image'        => ['nullable', 'string', 'max:2048'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }
}
