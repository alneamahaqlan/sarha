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
            // When true the price is a "starting from" minimum.
            'price_from'     => ['nullable', 'boolean'],
            'price_includes' => ['nullable', 'string', 'max:2000'],
            'price_excludes' => ['nullable', 'string', 'max:2000'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }
}
