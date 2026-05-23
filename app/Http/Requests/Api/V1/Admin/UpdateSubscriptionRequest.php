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
            'clinic_id'          => ['sometimes', 'required', 'integer', 'exists:clinics,id'],
            'type'               => ['sometimes', 'required', 'in:basic,premium'],
            'amount'             => ['sometimes', 'required', 'numeric', 'min:0'],
            'starts_at'          => ['sometimes', 'required', 'date'],
            'ends_at'            => ['sometimes', 'required', 'date'],
            'status'             => ['sometimes', 'required', 'in:active,expired,cancelled,pending_payment'],
            'moyasar_payment_id' => ['nullable', 'string', 'max:255'],
            'notes'              => ['nullable', 'string'],
        ];
    }
}
