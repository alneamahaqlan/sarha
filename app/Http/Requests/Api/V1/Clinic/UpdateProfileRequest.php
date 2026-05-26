<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        // Mirrors ClinicProfile page form fields exactly.
        return [
            'name'        => ['sometimes', 'required', 'string', 'max:255'],
            'phone'       => ['sometimes', 'required', 'string', 'max:20'],
            'email'       => ['nullable', 'email', 'max:255'],
            'address'     => ['nullable', 'string'],
            'district'    => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'website'     => ['nullable', 'url'],
            'instagram'   => ['nullable', 'string', 'max:255'],
            'twitter'     => ['nullable', 'string', 'max:255'],
            'snapchat'    => ['nullable', 'string', 'max:255'],
            'logo'        => ['nullable', 'string'],
            'latitude'    => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'   => ['nullable', 'numeric', 'between:-180,180'],
            'google_place_id' => ['nullable', 'string', 'max:255'],
            'password'    => ['nullable', Password::min(8)],
        ];
    }
}
