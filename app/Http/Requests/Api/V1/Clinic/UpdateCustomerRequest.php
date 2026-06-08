<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Support\ActingClinicUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ActingClinicUser::can('customers.manage');
    }

    public function rules(): array
    {
        return [
            'name'              => ['sometimes', 'required', 'string', 'max:120'],
            'email'             => ['nullable', 'email', 'max:191'],
            'marketing_opt_out' => ['sometimes', 'boolean'],
            'follow_up_priority'=> ['sometimes', 'integer', 'between:0,3'],
        ];
    }
}
