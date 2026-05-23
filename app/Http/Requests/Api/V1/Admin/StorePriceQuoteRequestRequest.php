<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePriceQuoteRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('create', \App\Models\PriceQuoteRequest::class);
    }

    public function rules(): array
    {
        return [
            'clinic_id'      => ['required', 'integer', 'exists:clinics,id'],
            'customer_name'  => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'service_name'   => ['required', 'string', 'max:255'],
            'status'         => ['required', 'in:new,replied,closed'],
            'description'    => ['nullable', 'string'],
            'clinic_reply'   => ['nullable', 'string'],
        ];
    }
}
