<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'acquisition_source' => ['sometimes', 'required', Rule::in(Booking::ACQUISITION_SOURCES)],
            // Moving a card to a custom Kanban stage. Must belong to the
            // acting clinic; status is sent alongside when the kind changes.
            'stage_id'       => ['sometimes', 'nullable', Rule::exists('clinic_booking_stages', 'id')
                ->where('clinic_id', (int) $this->user('clinic')?->getKey())],
            // Optional context attached to the status transition —
            // logged into clinic_activity_logs.summary so the
            // Kanban Timeline can render "Cancelled: schedule_conflict".
            'cancel_reason'  => ['nullable', 'in:patient_cancelled,no_answer,schedule_conflict,other'],
            'cancel_note'    => ['nullable', 'string', 'max:500'],
            'completion_note'=> ['nullable', 'string', 'max:500'],
        ];
    }
}
