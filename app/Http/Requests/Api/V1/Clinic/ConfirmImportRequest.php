<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the rows the clinic confirmed from the AI review table. Both
 * arrays are optional individually, but at least one row total is required so
 * we never run an empty save. Foreign keys (category_ids, service_id) are
 * re-checked against this clinic in ImportServicesService — here we only shape
 * and bound the payload.
 */
class ConfirmImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        return [
            'services'                  => ['array'],
            'services.*.name'           => ['required', 'string', 'max:255'],
            'services.*.description'    => ['nullable', 'string', 'max:5000'],
            'services.*.price'          => ['nullable', 'numeric', 'min:0'],
            'services.*.category_ids'   => ['nullable', 'array', 'max:5'],
            'services.*.category_ids.*' => ['integer'],

            'offers'                    => ['array'],
            'offers.*.title'            => ['required', 'string', 'max:255'],
            'offers.*.description'      => ['nullable', 'string', 'max:2000'],
            'offers.*.old_price'        => ['nullable', 'numeric', 'min:0'],
            'offers.*.price'            => ['nullable', 'numeric', 'min:0'],
            'offers.*.service_id'       => ['nullable', 'integer'],
            'offers.*.ends_at'          => ['nullable', 'date'],
        ];
    }

    public function after(): array
    {
        return [
            function (\Illuminate\Validation\Validator $validator) {
                $services = $this->input('services', []);
                $offers   = $this->input('offers', []);
                if (count($services) === 0 && count($offers) === 0) {
                    $validator->errors()->add('services', __('admin.import_nothing_selected'));
                }
            },
        ];
    }
}
