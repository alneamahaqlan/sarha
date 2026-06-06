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
            // Either a customer (contact reminder) or a title (general task)
            // — at least one must be present.
            'customer_id'        => ['nullable', 'integer', 'exists:customers,id'],
            'title'              => ['nullable', 'string', 'max:255', 'required_without:customer_id'],
            'booking_id'         => ['nullable', 'integer', 'exists:bookings,id'],
            'assignee_member_id' => ['nullable', 'integer', 'exists:clinic_team_members,id'],
            // Must be in the future — a reminder for a past moment would
            // fire on the very next scheduler pass, which is never intended.
            'remind_at'   => ['required', 'date', 'after:now'],
            'note'        => ['nullable', 'string', 'max:1000'],
        ];
    }
}
