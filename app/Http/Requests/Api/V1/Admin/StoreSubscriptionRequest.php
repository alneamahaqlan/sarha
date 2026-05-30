<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Inputs the admin types in after a bank transfer arrives. The
 * controller hands these off to SubscriptionService::activate(),
 * which derives amount + dates so the admin can't miskey them.
 */
class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('create', Subscription::class);
    }

    public function rules(): array
    {
        return [
            'clinic_id'               => ['required', 'integer', 'exists:clinics,id'],
            'subscription_package_id' => ['required', 'integer', 'exists:subscription_packages,id'],
            'billing_cycle'           => ['required', 'in:' . Subscription::CYCLE_QUARTERLY . ',' . Subscription::CYCLE_ANNUAL],
            'bonus_months'            => ['nullable', 'integer', 'min:0', 'max:24'],
            'notes'                   => ['nullable', 'string', 'max:2000'],
        ];
    }
}
