<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('create', \App\Models\Service::class);
    }

    public function rules(): array
    {
        return [
            'clinic_id'    => ['required', 'integer', 'exists:clinics,id'],
            // Every service must be tagged with an active admin-managed category.
            'category_id'  => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'name'         => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'price'        => ['required', 'numeric', 'min:0'],
            // Relative path returned by the /uploads endpoint (e.g. services/x.jpg).
            'image'        => ['nullable', 'string', 'max:2048'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }
}
