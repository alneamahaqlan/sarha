<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Clinic-side manual booking creation (walk-in or phone booking
 * entered by the team). Distinct from the public customer-side
 * booking flow (which lives in App\Http\Controllers\Api\V1\Public\…)
 * because the clinic stamps source=clinic, defaults status, and
 * optionally pre-assigns to a team member.
 */
class CreateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null
            && $this->user('clinic')->can('create', Booking::class);
    }

    public function rules(): array
    {
        return [
            'customer_name'  => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:32'],
            'service_id'     => ['nullable', 'integer', 'exists:services,id'],
            'appointment_at' => ['nullable', 'date'],
            'notes'          => ['nullable', 'string', 'max:1000'],
            'clinic_notes'   => ['nullable', 'string', 'max:1000'],
            'status'         => ['nullable', Rule::in(['new', 'contacted', 'appointment_set'])],
            'acquisition_source' => ['nullable', Rule::in(Booking::ACQUISITION_SOURCES)],
            'assignee_type'  => ['nullable', Rule::in(['Clinic', 'ClinicTeamMember'])],
            'assignee_id'    => ['nullable', 'integer'],
        ];
    }
}
