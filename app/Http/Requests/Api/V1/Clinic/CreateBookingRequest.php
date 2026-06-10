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
            // Same Saudi-mobile policy the public booking / OTP / cart flows
            // enforce (^05XXXXXXXX) — clinic staff must enter a valid number,
            // not a partial or malformed one.
            'customer_phone' => ['required', 'string', 'max:20', 'regex:/^05\d{8}$/'],
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

    public function messages(): array
    {
        return [
            'customer_phone.regex' => __('site.phone_invalid'),
        ];
    }

    /** Trim the phone so trailing spaces never trip the strict regex. */
    protected function prepareForValidation(): void
    {
        if ($this->has('customer_phone')) {
            $this->merge(['customer_phone' => trim((string) $this->input('customer_phone'))]);
        }
    }
}
