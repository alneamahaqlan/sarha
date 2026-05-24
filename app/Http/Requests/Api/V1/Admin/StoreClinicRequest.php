<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('create', \App\Models\Clinic::class);
    }

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:255'],
            'slug'              => ['nullable', 'string', 'max:255', 'unique:clinics,slug'],
            'phone'             => ['required', 'string', 'max:20'],
            'email'             => ['nullable', 'email', 'max:255'],
            'license_number'    => ['nullable', 'string', 'max:255'],
            'password'          => ['required', Password::min(8)],
            'city_id'           => ['required', 'integer', 'exists:cities,id'],
            'address'           => ['nullable', 'string'],
            'district'          => ['nullable', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'status'            => ['required', 'in:pending,active,suspended,rejected'],
            'subscription_type' => ['nullable', 'in:basic,premium'],
            'subscription_starts_at' => ['nullable', 'date'],
            'subscription_ends_at'   => ['nullable', 'date'],
            'is_featured'       => ['nullable', 'boolean'],
            'rejection_reason'  => ['nullable', 'string'],
            'website'           => ['nullable', 'url'],
            'instagram'         => ['nullable', 'string', 'max:255'],
            'twitter'           => ['nullable', 'string', 'max:255'],
            'snapchat'          => ['nullable', 'string', 'max:255'],
            'tiktok'            => ['nullable', 'string', 'max:255'],
            'google_place_id'   => ['nullable', 'string', 'max:255'],
            'maps_url'          => ['nullable', 'url', 'max:2048'],
            'logo'              => ['nullable', 'string'],
            'gallery'           => ['nullable', 'array'],
            'gallery.*'         => ['string'],
            'categories'        => ['nullable', 'array'],
            'categories.*'      => ['integer', 'exists:categories,id'],
        ];
    }
}
