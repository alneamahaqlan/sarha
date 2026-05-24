<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        return [
            'body'      => ['required', 'string', 'max:2000'],
            'price'     => ['nullable', 'numeric', 'min:0'],
            'is_public' => ['nullable', 'boolean'],
        ];
    }
}
