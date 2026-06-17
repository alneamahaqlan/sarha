<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Models\RewardVoucher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a manual reward grant to a single phone. Same shape rules as
 * the auto-grant rule; offer/service must belong to the acting clinic.
 */
class GrantRewardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('clinic')->check();
    }

    public function rules(): array
    {
        $clinicId = (int) auth('clinic')->id();

        return [
            'phone'          => ['required', 'string', 'regex:/^05\d{8}$/'],
            'type'           => ['required', Rule::in([RewardVoucher::TYPE_OFFER_DISCOUNT, RewardVoucher::TYPE_FREE_SERVICE])],
            'offer_id'       => ['nullable', 'integer', 'required_if:type,' . RewardVoucher::TYPE_OFFER_DISCOUNT, Rule::exists('offers', 'id')->where('clinic_id', $clinicId)],
            'service_id'     => ['nullable', 'integer', 'required_if:type,' . RewardVoucher::TYPE_FREE_SERVICE, Rule::exists('services', 'id')->where('clinic_id', $clinicId)],
            'discount_type'  => ['nullable', 'required_if:type,' . RewardVoucher::TYPE_OFFER_DISCOUNT, Rule::in([RewardVoucher::DISCOUNT_PERCENT, RewardVoucher::DISCOUNT_AMOUNT])],
            'discount_value' => ['nullable', 'required_if:type,' . RewardVoucher::TYPE_OFFER_DISCOUNT, 'numeric', 'gt:0'],
            'expires_in_days'=> ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('type') === RewardVoucher::TYPE_OFFER_DISCOUNT
                && $this->input('discount_type') === RewardVoucher::DISCOUNT_PERCENT
                && $this->input('discount_value') !== null
                && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', __('validation.max.numeric', ['attribute' => 'discount_value', 'max' => 100]));
            }
        });
    }
}
