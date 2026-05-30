<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null
            && $this->user('clinic')->can('update', $this->route('booking'));
    }

    public function rules(): array
    {
        // Clinic panel only exposes status, appointment_at, clinic_notes
        // (customer_name / customer_phone are disabled in Filament).
        return [
            'status'         => ['sometimes', 'required', 'in:new,contacted,appointment_set,completed,no_show,cancelled'],
            'appointment_at' => ['nullable', 'date'],
            'clinic_notes'   => ['nullable', 'string'],
            // Optional context attached to the status transition —
            // logged into clinic_activity_logs.summary so the
            // Kanban Timeline can render "Cancelled: schedule_conflict".
            'cancel_reason'  => ['nullable', 'in:patient_cancelled,no_answer,schedule_conflict,other'],
            'cancel_note'    => ['nullable', 'string', 'max:500'],
            'completion_note'=> ['nullable', 'string', 'max:500'],
        ];
    }
}
