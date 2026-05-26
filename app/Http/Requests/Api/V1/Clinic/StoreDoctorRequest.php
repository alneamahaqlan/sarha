<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class StoreDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        $clinicId = (int) $this->user('clinic')->id;

        return [
            'name'             => ['required', 'string', 'max:255'],
            'specialty'        => ['nullable', 'string', 'max:255'],
            'gender'           => ['nullable', 'in:male,female'],
            'sub_clinic_id'    => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('sub_clinics', 'id')->where('clinic_id', $clinicId)],
            'photo'            => ['nullable', 'string', 'max:2048'],
            'bio'              => ['nullable', 'string', 'max:2000'],
            'qualifications'   => ['nullable', 'string', 'max:2000'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:70'],
            'university'       => ['nullable', 'string', 'max:255'],
            'languages'        => ['nullable', 'string', 'max:255'],
            'is_active'        => ['nullable', 'boolean'],
            'sort_order'       => ['nullable', 'integer', 'min:0'],
        ];
    }
}
