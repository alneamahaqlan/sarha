<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePriceQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null
            && $this->user('clinic')->can('update', $this->route('priceQuote'));
    }

    public function rules(): array
    {
        // Mirrors Clinic PriceQuoteRequestResource: clinic_reply required when status=replied.
        return [
            'status'       => ['sometimes', 'required', 'in:new,replied,closed'],
            'clinic_reply' => ['nullable', 'required_if:status,replied', 'string'],
        ];
    }
}
