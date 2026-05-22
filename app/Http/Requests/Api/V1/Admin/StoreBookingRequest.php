<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('create', \App\Models\Booking::class);
    }

    public function rules(): array
    {
        return [
            'clinic_id'      => ['required', 'integer', 'exists:clinics,id'],
            'customer_name'  => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'service_id'     => ['nullable', 'integer', 'exists:services,id'],
            'status'         => ['required', 'in:new,contacted,appointment_set,completed,no_show,cancelled'],
            'appointment_at' => ['nullable', 'date'],
            'notes'          => ['nullable', 'string'],
            'clinic_notes'   => ['nullable', 'string'],
        ];
    }
}
