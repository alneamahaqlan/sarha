<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePriceQuoteRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('update', $this->route('priceQuote'));
    }

    public function rules(): array
    {
        return [
            'clinic_id'      => ['sometimes', 'required', 'integer', 'exists:clinics,id'],
            'customer_name'  => ['sometimes', 'required', 'string', 'max:255'],
            'customer_phone' => ['sometimes', 'required', 'string', 'max:20'],
            'service_name'   => ['sometimes', 'required', 'string', 'max:255'],
            'status'         => ['sometimes', 'required', 'in:new,replied,closed'],
            'description'    => ['nullable', 'string'],
            'clinic_reply'   => ['nullable', 'string'],
        ];
    }
}
