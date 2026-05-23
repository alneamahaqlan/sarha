<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('create', \App\Models\Complaint::class);
    }

    public function rules(): array
    {
        return [
            'clinic_id'         => ['nullable', 'integer', 'exists:clinics,id'],
            'customer_name'     => ['required', 'string', 'max:255'],
            'customer_phone'    => ['required', 'string', 'max:20'],
            'customer_email'    => ['nullable', 'email', 'max:255'],
            'type'              => ['required', 'in:quality,pricing,misleading_info,other'],
            'priority'          => ['required', 'in:low,medium,high'],
            'status'            => ['required', 'in:new,in_review,resolved,rejected'],
            'assigned_admin_id' => ['nullable', 'integer', 'exists:admins,id'],
            'subject'           => ['required', 'string', 'max:255'],
            'description'       => ['required', 'string'],
            'admin_notes'       => ['nullable', 'string'],
            'resolution'        => ['nullable', 'string'],
        ];
    }
}
