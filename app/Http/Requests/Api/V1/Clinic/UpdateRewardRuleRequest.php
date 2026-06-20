<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Models\RewardVoucher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the clinic's single auto-grant rule. Shape rules:
 *   - offer_discount → requires offer_id + discount_type + discount_value
 *   - free_service   → requires service_id
 * The conditional shape is only enforced when the rule is `enabled`, so a
 * clinic can save a disabled draft freely. Offer/service must belong to
 * the acting clinic.
 */
class UpdateRewardRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('clinic')->check();
    }

    public function rules(): array
    {
        $clinicId = (int) auth('clinic')->id();

        return [
            'enabled'        => ['required', 'boolean'],
            'type'           => ['nullable', Rule::in([RewardVoucher::TYPE_OFFER_DISCOUNT, RewardVoucher::TYPE_FREE_SERVICE])],
            'offer_id'       => ['nullable', 'integer', Rule::exists('offers', 'id')->where('clinic_id', $clinicId)],
            'service_id'     => ['nullable', 'integer', Rule::exists('services', 'id')->where('clinic_id', $clinicId)],
            'discount_type'  => ['nullable', Rule::in([RewardVoucher::DISCOUNT_PERCENT, RewardVoucher::DISCOUNT_AMOUNT])],
            'discount_value' => ['nullable', 'numeric', 'gt:0'],
            'validity_days'  => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->boolean('enabled')) {
                return; // a disabled rule can be a blank draft
            }

            $type = $this->input('type');
            if (! $type) {
                $validator->errors()->add('type', __('validation.required', ['attribute' => 'type']));
                return;
            }

            if ($type === RewardVoucher::TYPE_OFFER_DISCOUNT) {
                if (! $this->input('offer_id'))      $validator->errors()->add('offer_id', __('validation.required', ['attribute' => 'offer']));
                if (! $this->input('discount_type')) $validator->errors()->add('discount_type', __('validation.required', ['attribute' => 'discount_type']));
                if ($this->input('discount_value') === null) $validator->errors()->add('discount_value', __('validation.required', ['attribute' => 'discount_value']));
            } elseif ($type === RewardVoucher::TYPE_FREE_SERVICE) {
                if (! $this->input('service_id')) $validator->errors()->add('service_id', __('validation.required', ['attribute' => 'service']));
            }

            // A percentage discount can never exceed 100%.
            if ($this->input('discount_type') === RewardVoucher::DISCOUNT_PERCENT
                && $this->input('discount_value') !== null
                && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', __('validation.max.numeric', ['attribute' => 'discount_value', 'max' => 100]));
            }
        });
    }
}
