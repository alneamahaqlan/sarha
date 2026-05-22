<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        $clinicId = $this->user('clinic')->id;

        return [
            'name'               => ['required', 'string', 'max:255'],
            // Category / sub-clinic must belong to the authenticated clinic (Filament scopes options to clinic_id).
            'custom_category_id' => ['nullable', 'integer', Rule::exists('custom_categories', 'id')->where('clinic_id', $clinicId)],
            'sub_clinic_id'      => ['nullable', 'integer', Rule::exists('sub_clinics', 'id')->where('clinic_id', $clinicId)],
            'description'        => ['nullable', 'string'],
            'price'              => ['required', 'numeric', 'min:0'],
            'old_price'          => ['nullable', 'numeric', 'min:0'],
            'offer_expires_at'   => ['nullable', 'date', 'after:today'],
            'is_active'          => ['nullable', 'boolean'],
            'sort_order'         => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // Mirrors the inline closure rule in Clinic ServiceResource: old_price must be > price,
        // and offer_expires_at required whenever old_price is present.
        $validator->after(function (Validator $v) {
            $oldPrice = $this->input('old_price');
            if ($oldPrice !== null && $oldPrice !== '' && (float) $oldPrice <= (float) $this->input('price')) {
                $v->errors()->add('old_price', __('admin.validation.old_price_higher'));
            }
            if (filled($oldPrice) && blank($this->input('offer_expires_at'))) {
                $v->errors()->add('offer_expires_at', __('validation.required', ['attribute' => 'offer_expires_at']));
            }
        });
    }
}
