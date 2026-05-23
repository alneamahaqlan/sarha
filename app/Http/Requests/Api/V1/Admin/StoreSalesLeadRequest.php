<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('create', \App\Models\SalesLead::class);
    }

    public function rules(): array
    {
        return [
            'clinic_name'       => ['required', 'string', 'max:255'],
            'contact_name'      => ['nullable', 'string', 'max:255'],
            'phone'             => ['required', 'string', 'max:20'],
            'email'             => ['nullable', 'email', 'max:255'],
            'license_number'    => ['nullable', 'string', 'max:100'],
            'city_id'           => ['nullable', 'integer', 'exists:cities,id'],
            'district'          => ['nullable', 'string', 'max:255'],
            'address'           => ['nullable', 'string'],
            'status'            => ['required', 'in:new,contacted,interested,negotiating,converted,lost'],
            'assigned_to'       => ['nullable', 'integer', 'exists:admins,id'],
            'next_follow_up_at' => ['nullable', 'date'],
            'last_contact_at'   => ['nullable', 'date'],
            'notes'             => ['nullable', 'string'],
            'sales_notes'       => ['nullable', 'string'],
        ];
    }
}
