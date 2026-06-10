<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBeforeAfterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        $clinicId = (int) $this->user('clinic')->id;

        return [
            'title'         => ['nullable', 'string', 'max:255'],
            'before_image'  => ['required', 'string', 'max:2048'],
            'after_image'   => ['required', 'string', 'max:2048'],
            'display_mode'  => ['nullable', 'string', Rule::in(['side_by_side', 'slider'])],
            // Optional links — must belong to the authenticated complex.
            'sub_clinic_id' => ['nullable', 'integer', Rule::exists('sub_clinics', 'id')->where('clinic_id', $clinicId)],
            'service_id'    => ['nullable', 'integer', Rule::exists('services', 'id')->where('clinic_id', $clinicId)],
            'is_active'     => ['nullable', 'boolean'],
            'sort_order'    => ['nullable', 'integer', 'min:0'],
        ];
    }
}
