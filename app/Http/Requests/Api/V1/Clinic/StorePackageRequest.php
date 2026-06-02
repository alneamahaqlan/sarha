<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        $clinicId = (int) $this->user('clinic')->id;

        return [
            'name'         => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'price'        => ['required', 'numeric', 'min:0'],
            'old_price'    => ['nullable', 'numeric', 'min:0'],
            'expires_at'   => ['nullable', 'date'],
            'is_active'    => ['nullable', 'boolean'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
            'service_ids'  => ['nullable', 'array'],
            'service_ids.*' => ['integer', Rule::exists('services', 'id')->where('clinic_id', $clinicId)],
            // Optional per-service note, keyed by service id (e.g. "includes a discount card").
            'service_notes'   => ['nullable', 'array'],
            'service_notes.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
