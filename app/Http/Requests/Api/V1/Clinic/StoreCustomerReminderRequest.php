<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Support\ActingClinicUser;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ActingClinicUser::can('reminders.create');
    }

    public function rules(): array
    {
        return [
            'customer_id'        => ['required', 'integer', 'exists:customers,id'],
            'booking_id'         => ['nullable', 'integer', 'exists:bookings,id'],
            'assignee_member_id' => ['nullable', 'integer', 'exists:clinic_team_members,id'],
            // Must be in the future — a reminder for a past moment would
            // fire on the very next scheduler pass, which is never intended.
            'remind_at'   => ['required', 'date', 'after:now'],
            'note'        => ['nullable', 'string', 'max:1000'],
        ];
    }
}
