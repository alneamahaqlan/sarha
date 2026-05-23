<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('create', \App\Models\Subscription::class);
    }

    public function rules(): array
    {
        return [
            'clinic_id'          => ['required', 'integer', 'exists:clinics,id'],
            'type'               => ['required', 'in:basic,premium'],
            'amount'             => ['required', 'numeric', 'min:0'],
            'starts_at'          => ['required', 'date'],
            'ends_at'            => ['required', 'date', 'after:starts_at'],
            'status'             => ['required', 'in:active,expired,cancelled,pending_payment'],
            'moyasar_payment_id' => ['nullable', 'string', 'max:255'],
            'notes'              => ['nullable', 'string'],
        ];
    }
}
