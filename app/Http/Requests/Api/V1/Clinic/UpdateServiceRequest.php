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
            // Doctors who provide this service — each must belong to the
            // authenticated complex. Synced only when present in the payload.
            'doctor_ids'         => ['sometimes', 'nullable', 'array'],
            'doctor_ids.*'       => ['integer', 'distinct', Rule::exists('doctors', 'id')->where('clinic_id', $clinicId)],
            'description'        => ['nullable', 'string'],
            'price'              => ['sometimes', 'required', 'numeric', 'min:0'],
            // Optional discounted price — upserts the service's inline offer.
            'offer_price'        => ['nullable', 'numeric', 'min:0'],
            'offer_ends_at'      => ['nullable', 'date', 'after:today'],
            // Relative path returned by the /uploads endpoint (e.g. services/x.jpg).
            'image'              => ['nullable', 'string', 'max:2048'],
            // When true the price is a "starting from" minimum.
            'price_from'         => ['nullable', 'boolean'],
            'price_includes'     => ['nullable', 'string', 'max:2000'],
            'price_excludes'     => ['nullable', 'string', 'max:2000'],
            'is_active'          => ['nullable', 'boolean'],
            'sort_order'         => ['nullable', 'integer'],
        ];
    }
}
