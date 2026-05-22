<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('create', \App\Models\Service::class);
    }

    public function rules(): array
    {
        return [
            'clinic_id'        => ['required', 'integer', 'exists:clinics,id'],
            'name'             => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'price'            => ['required', 'numeric', 'min:0'],
            'old_price'        => ['nullable', 'numeric', 'min:0'],
            'offer_expires_at' => ['nullable', 'date', 'after:today'],
            'is_active'        => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // Mirrors the inline closure rule in ServiceResource: old_price must be strictly > price.
        $validator->after(function (Validator $v) {
            $oldPrice = $this->input('old_price');
            $price = $this->input('price');
            if ($oldPrice !== null && $oldPrice !== '' && (float) $oldPrice <= (float) $price) {
                $v->errors()->add('old_price', __('admin.validation.old_price_higher'));
            }

            // Mirrors required(filled('old_price')) — offer_expires_at must be present when discount is present.
            if (filled($oldPrice) && blank($this->input('offer_expires_at'))) {
                $v->errors()->add('offer_expires_at', __('validation.required', ['attribute' => 'offer_expires_at']));
            }
        });
    }
}
