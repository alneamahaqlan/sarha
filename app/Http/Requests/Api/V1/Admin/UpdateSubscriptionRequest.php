<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('update', $this->route('subscription'));
    }

    public function rules(): array
    {
        return [
            'clinic_id'               => ['sometimes', 'required', 'integer', 'exists:clinics,id'],
            'subscription_package_id' => ['sometimes', 'required', 'integer', 'exists:subscription_packages,id'],
            'billing_cycle'           => ['sometimes', 'in:quarterly,annual'],
            'bonus_months'            => ['sometimes', 'integer', 'min:0', 'max:24'],
            // `type` is the legacy slug column — accept any non-empty string
            // since admin-defined packages can carry custom slugs now.
            'type'                    => ['sometimes', 'required', 'string', 'max:32'],
            'amount'                  => ['sometimes', 'required', 'numeric', 'min:0'],
            'starts_at'               => ['sometimes', 'required', 'date'],
            // ends_at must not predate starts_at — without this guard, an
            // admin PATCH can create a negative-duration sub that confuses
            // every downstream sort/filter that reads the date pair.
            'ends_at'                 => ['sometimes', 'required', 'date', 'after_or_equal:starts_at'],
            'status'                  => ['sometimes', 'required', 'in:active,expired,cancelled,pending_payment'],
            'moyasar_payment_id'      => ['nullable', 'string', 'max:255'],
            'notes'                   => ['nullable', 'string'],
        ];
    }
}
