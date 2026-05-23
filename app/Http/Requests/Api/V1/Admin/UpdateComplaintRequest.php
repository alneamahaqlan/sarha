<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('update', $this->route('complaint'));
    }

    public function rules(): array
    {
        return [
            'clinic_id'         => ['nullable', 'integer', 'exists:clinics,id'],
            'customer_name'     => ['sometimes', 'required', 'string', 'max:255'],
            'customer_phone'    => ['sometimes', 'required', 'string', 'max:20'],
            'customer_email'    => ['nullable', 'email', 'max:255'],
            'type'              => ['sometimes', 'required', 'in:quality,pricing,misleading_info,other'],
            'priority'          => ['sometimes', 'required', 'in:low,medium,high'],
            'status'            => ['sometimes', 'required', 'in:new,in_review,resolved,rejected'],
            'assigned_admin_id' => ['nullable', 'integer', 'exists:admins,id'],
            'subject'           => ['sometimes', 'required', 'string', 'max:255'],
            'description'       => ['sometimes', 'required', 'string'],
            'admin_notes'       => ['nullable', 'string'],
            'resolution'        => ['nullable', 'string'],
        ];
    }
}
