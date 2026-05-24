<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('update', $this->route('clinic'));
    }

    public function rules(): array
    {
        $id = $this->route('clinic')?->id;

        return [
            'name'              => ['sometimes', 'required', 'string', 'max:255'],
            'slug'              => ['nullable', 'string', 'max:255', Rule::unique('clinics', 'slug')->ignore($id)],
            'phone'             => ['sometimes', 'required', 'string', 'max:20'],
            'email'             => ['nullable', 'email', 'max:255'],
            'license_number'    => ['nullable', 'string', 'max:255'],
            'password'          => ['nullable', Password::min(8)],
            'city_id'           => ['sometimes', 'required', 'integer', 'exists:cities,id'],
            'address'           => ['nullable', 'string'],
            'district'          => ['nullable', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'status'            => ['sometimes', 'required', 'in:pending,active,suspended,rejected'],
            'subscription_type' => ['nullable', 'in:basic,premium'],
            'subscription_starts_at' => ['nullable', 'date'],
            'subscription_ends_at'   => ['nullable', 'date'],
            'is_featured'       => ['nullable', 'boolean'],
            'rejection_reason'  => ['nullable', 'string'],
            'website'           => ['nullable', 'url'],
            'instagram'         => ['nullable', 'string', 'max:255'],
            'twitter'           => ['nullable', 'string', 'max:255'],
            'snapchat'          => ['nullable', 'string', 'max:255'],
            'google_place_id'   => ['nullable', 'string', 'max:255'],
            'logo'              => ['nullable', 'string'],
            'gallery'           => ['nullable', 'array'],
            'gallery.*'         => ['string'],
            'categories'        => ['nullable', 'array'],
            'categories.*'      => ['integer', 'exists:categories,id'],
        ];
    }
}
