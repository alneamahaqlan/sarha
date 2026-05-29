<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Models\Offer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        $clinicId = $this->user('clinic')->id;

        return [
            'type'         => ['sometimes', 'required', Rule::in([Offer::TYPE_SERVICE, Offer::TYPE_GENERAL])],
            'service_id'   => ['nullable', 'integer', Rule::exists('services', 'id')->where('clinic_id', $clinicId)],
            'title'        => ['sometimes', 'required', 'string', 'max:255'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'image'        => ['nullable', 'string', 'max:500'],
            'old_price'    => ['nullable', 'numeric', 'min:0'],
            'price'        => ['nullable', 'numeric', 'min:0'],
            'starts_at'    => ['sometimes', 'nullable', 'date'],
            'ends_at'      => ['sometimes', 'required', 'date', 'after:starts_at'],
            'is_featured'  => ['sometimes', 'boolean'],
            'is_active'    => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // Fall back to the existing row's values when the patch
            // payload only changes a subset of the type/service_id pair,
            // so the invariant check doesn't fire on unrelated edits.
            $offer = $this->route('offer');
            $type = $this->input('type', $offer?->type);
            $serviceId = $this->has('service_id') ? $this->input('service_id') : $offer?->service_id;

            if ($type === Offer::TYPE_SERVICE && empty($serviceId)) {
                $v->errors()->add('service_id', __('validation.required', ['attribute' => 'service_id']));
            }
            if ($type === Offer::TYPE_GENERAL && ! empty($serviceId)) {
                $v->errors()->add('service_id', __('clinic_offers.service_id_not_allowed_for_general'));
            }

            $oldPrice = $this->input('old_price', $offer?->old_price);
            $price    = $this->input('price', $offer?->price);
            if ($oldPrice !== null && $oldPrice !== '' && $price !== null && $price !== ''
                && (float) $oldPrice <= (float) $price) {
                $v->errors()->add('old_price', __('admin.validation.old_price_higher'));
            }
        });
    }
}
