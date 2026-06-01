<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ConvertSalesLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('convert', $this->route('salesLead'));
    }

    public function rules(): array
    {
        return [
            // Package = the tier (features + default price), chosen from the
            // subscription_packages catalogue (the single source of truth).
            'package_id'    => ['required', 'integer', 'exists:subscription_packages,id'],
            'billing_cycle' => ['required', 'in:quarterly,annual'],
            // Manual per-subscription price. Optional: when omitted the
            // package-derived default (monthly_price × cycle months) is used.
            'amount'        => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ];
    }
}
