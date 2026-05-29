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
            // Every service must be tagged with 1–5 active admin-managed
            // specialties (same lookup the clinic itself uses). Only ACTIVE
            // rows are valid picks so a deprecated specialty can't be
            // selected anew.
            'category_ids'       => ['required', 'array', 'min:1', 'max:5'],
            'category_ids.*'     => ['integer', 'distinct', Rule::exists('categories', 'id')->where('is_active', true)],
            // The clinic (sub-clinic) a service belongs to must be owned by the authenticated complex.
            'sub_clinic_id'      => ['nullable', 'integer', Rule::exists('sub_clinics', 'id')->where('clinic_id', $clinicId)],
            'description'        => ['nullable', 'string'],
            'price'              => ['required', 'numeric', 'min:0'],
            'is_active'          => ['nullable', 'boolean'],
            'sort_order'         => ['nullable', 'integer'],
        ];
    }
}
