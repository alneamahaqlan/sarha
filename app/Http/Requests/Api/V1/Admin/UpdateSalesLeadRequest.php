<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SalesLead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('update', $this->route('salesLead'));
    }

    public function rules(): array
    {
        return [
            'clinic_name'       => ['sometimes', 'required', 'string', 'max:255'],
            'contact_name'      => ['nullable', 'string', 'max:255'],
            'phone'             => ['sometimes', 'required', 'string', 'max:20'],
            'email'             => ['nullable', 'email', 'max:255'],
            'license_number'    => ['nullable', 'string', 'max:100'],
            'city_id'           => ['nullable', 'integer', 'exists:cities,id'],
            'district'          => ['nullable', 'string', 'max:255'],
            'address'           => ['nullable', 'string'],
            'status'            => ['sometimes', 'required', 'in:new,contacted,interested,negotiating,converted,lost'],
            'source'            => ['nullable', Rule::in(SalesLead::SOURCES)],
            'lost_reason'       => ['nullable', Rule::in(SalesLead::LOST_REASONS)],
            'lost_notes'        => ['nullable', 'string', 'max:1000'],
            'assigned_to'       => ['nullable', 'integer', 'exists:admins,id'],
            'next_follow_up_at' => ['nullable', 'date'],
            'last_contact_at'   => ['nullable', 'date'],
            'notes'             => ['nullable', 'string'],
            'sales_notes'       => ['nullable', 'string'],
        ];
    }
}
