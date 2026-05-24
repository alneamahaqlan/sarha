<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null
            && $this->user('clinic')->can('update', $this->route('service'));
    }

    public function rules(): array
    {
        $clinicId = $this->user('clinic')->id;

        return [
            'name'               => ['sometimes', 'required', 'string', 'max:255'],
            'sub_clinic_id'      => ['nullable', 'integer', Rule::exists('sub_clinics', 'id')->where('clinic_id', $clinicId)],
            'description'        => ['nullable', 'string'],
            'price'              => ['sometimes', 'required', 'numeric', 'min:0'],
            'old_price'          => ['nullable', 'numeric', 'min:0'],
            'offer_expires_at'   => ['nullable', 'date', 'after:today'],
            'is_active'          => ['nullable', 'boolean'],
            'sort_order'         => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $oldPrice = $this->input('old_price');
            $price = $this->input('price', $this->route('service')?->price);
            if ($oldPrice !== null && $oldPrice !== '' && (float) $oldPrice <= (float) $price) {
                $v->errors()->add('old_price', __('admin.validation.old_price_higher'));
            }
            if (filled($oldPrice) && blank($this->input('offer_expires_at'))) {
                $v->errors()->add('offer_expires_at', __('validation.required', ['attribute' => 'offer_expires_at']));
            }
        });
    }
}
