<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('update', $this->route('booking'));
    }

    public function rules(): array
    {
        return [
            'clinic_id'      => ['sometimes', 'required', 'integer', 'exists:clinics,id'],
            'customer_name'  => ['sometimes', 'required', 'string', 'max:255'],
            'customer_phone' => ['sometimes', 'required', 'string', 'max:20'],
            'service_id'     => ['nullable', 'integer', 'exists:services,id'],
            'status'         => ['sometimes', 'required', 'in:new,contacted,appointment_set,completed,no_show,cancelled'],
            'appointment_at' => ['nullable', 'date'],
            'notes'          => ['nullable', 'string'],
            'clinic_notes'   => ['nullable', 'string'],
        ];
    }
}
