<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reception redemption. An optional booking_id links the redemption to a
 * booking (the gate then enforces service/offer match); without it the
 * voucher is redeemed standalone (verbal verification). Falls back to the
 * voucher's reserved (applied) booking when omitted.
 */
class RedeemRewardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('clinic')->check();
    }

    public function rules(): array
    {
        $clinicId = (int) auth('clinic')->id();

        return [
            'booking_id' => ['nullable', 'integer', Rule::exists('bookings', 'id')->where('clinic_id', $clinicId)],
        ];
    }
}
