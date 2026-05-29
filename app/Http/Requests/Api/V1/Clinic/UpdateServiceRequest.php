<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null
            && $this->user('clinic')->can('update', $this->route('service'));
    }

    public function rules(): array
    {
        $clinicId = $this->user('clinic')->id;

        return [
            'name'               => ['sometimes', 'required', 'string', 'max:255'],
            // Specialties remain required on every edit — preserves the
            // invariant that every service is classified. Only enforced when
            // the field is actually present in the payload.
            'category_ids'       => ['sometimes', 'required', 'array', 'min:1', 'max:5'],
            'category_ids.*'     => ['integer', 'distinct', Rule::exists('categories', 'id')->where('is_active', true)],
            'sub_clinic_id'      => ['nullable', 'integer', Rule::exists('sub_clinics', 'id')->where('clinic_id', $clinicId)],
            'description'        => ['nullable', 'string'],
            'price'              => ['sometimes', 'required', 'numeric', 'min:0'],
            'is_active'          => ['nullable', 'boolean'],
            'sort_order'         => ['nullable', 'integer'],
        ];
    }
}
