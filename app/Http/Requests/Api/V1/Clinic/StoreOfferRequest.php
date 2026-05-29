<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Models\Offer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        $clinicId = $this->user('clinic')->id;

        return [
            'type'         => ['required', Rule::in([Offer::TYPE_SERVICE, Offer::TYPE_GENERAL])],
            // service_id presence depends on type — enforced in withValidator.
            // exists() rule still scoped to THIS clinic so an admin can't
            // attach an offer to a competitor's service via crafted payload.
            'service_id'   => ['nullable', 'integer', Rule::exists('services', 'id')->where('clinic_id', $clinicId)],
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string', 'max:2000'],
            // Image is the relative path on the public disk — upload
            // handled out of band by /api/v1/upload, same as banner slides.
            'image'        => ['nullable', 'string', 'max:500'],
            'old_price'    => ['nullable', 'numeric', 'min:0'],
            'price'        => ['nullable', 'numeric', 'min:0'],
            'starts_at'    => ['nullable', 'date'],
            'ends_at'      => ['required', 'date', 'after:starts_at'],
            'is_featured'  => ['nullable', 'boolean'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // Type-conditional checks: service-type offers MUST have a
            // service_id; general-type offers MUST NOT have one (so the
            // admin can't accidentally tie a "general" promo to a
            // specific service and mis-render the CTA).
            $type = $this->input('type');
            $serviceId = $this->input('service_id');
            if ($type === Offer::TYPE_SERVICE && empty($serviceId)) {
                $v->errors()->add('service_id', __('validation.required', ['attribute' => 'service_id']));
            }
            if ($type === Offer::TYPE_GENERAL && ! empty($serviceId)) {
                $v->errors()->add('service_id', __('clinic_offers.service_id_not_allowed_for_general'));
            }

            // Price sanity: if both prices are given, old > new.
            $oldPrice = $this->input('old_price');
            $price    = $this->input('price');
            if ($oldPrice !== null && $oldPrice !== '' && $price !== null && $price !== ''
                && (float) $oldPrice <= (float) $price) {
                $v->errors()->add('old_price', __('admin.validation.old_price_higher'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        // Default starts_at to "now" when admin omits it — the common
        // case for service-type offers that should go live immediately.
        if (! $this->has('starts_at') || $this->input('starts_at') === null) {
            $this->merge(['starts_at' => now()->toIso8601String()]);
        }
    }
}
