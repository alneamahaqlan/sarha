<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Optional: the clinic picked an existing canonical service from
            // the catalog typeahead. Must be an active catalog entry; the
            // resolver re-checks and falls back to name-matching if stale.
            'catalog_service_id' => ['nullable', 'integer', Rule::exists('catalog_services', 'id')->where('status', 'active')],
            // Every service must be tagged with 1–5 active admin-managed
            // specialties (same lookup the clinic itself uses). Only ACTIVE
            // rows are valid picks so a deprecated specialty can't be
            // selected anew.
            'category_ids'       => ['required', 'array', 'min:1', 'max:5'],
            'category_ids.*'     => ['integer', 'distinct', Rule::exists('categories', 'id')->where('is_active', true)],
            // The clinic (sub-clinic) a service belongs to must be owned by the authenticated complex.
            'sub_clinic_id'      => ['nullable', 'integer', Rule::exists('sub_clinics', 'id')->where('clinic_id', $clinicId)],
            // Doctors who provide this service — each must belong to the
            // authenticated complex. Optional; a service can have no doctors.
            'doctor_ids'         => ['nullable', 'array'],
            'doctor_ids.*'       => ['integer', 'distinct', Rule::exists('doctors', 'id')->where('clinic_id', $clinicId)],
            'description'        => ['nullable', 'string'],
            'price'              => ['required', 'numeric', 'min:0'],
            // Optional discounted price — creates a real offer for this service
            // (type=service). Must be below the regular price to be a discount.
            'offer_price'        => ['nullable', 'numeric', 'min:0', 'lt:price'],
            // When the discount ends (date). Defaults to +30 days when omitted.
            'offer_ends_at'      => ['nullable', 'date', 'after:today'],
            // Relative path returned by the /uploads endpoint (e.g. services/x.jpg).
            'image'              => ['nullable', 'string', 'max:2048'],
            // When true the price is a "starting from" minimum.
            'price_from'         => ['nullable', 'boolean'],
            'price_includes'     => ['nullable', 'string', 'max:2000'],
            'price_excludes'     => ['nullable', 'string', 'max:2000'],
            'is_active'          => ['nullable', 'boolean'],
            'sort_order'         => ['nullable', 'integer'],
        ];
    }
}
