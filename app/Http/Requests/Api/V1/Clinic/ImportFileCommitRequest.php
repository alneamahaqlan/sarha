<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Executes a file import: re-reads the previously uploaded file (referenced
 * by file_token) server-side, resolves every service name, creates the
 * bookings, and records the run. No re-pullable saved source for files.
 */
class ImportFileCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null
            && $this->user('clinic')->can('create', Booking::class);
    }

    public function rules(): array
    {
        $clinicId = $this->user('clinic')->id;

        return [
            // Opaque handle returned by the file preview step. Constrained to
            // the safe charset the controller writes (uuid + extension).
            'file_token'                 => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-]+\.(csv|txt|xlsx)$/'],
            'row_from'                   => ['required', 'integer', 'min:1'],
            'row_to'                     => ['required', 'integer', 'gte:row_from'],
            'column_map'                 => ['required', 'array'],
            'column_map.customer_name'   => ['required', 'string', 'max:4'],
            'column_map.customer_phone'  => ['required', 'string', 'max:4'],
            'column_map.service'         => ['nullable', 'string', 'max:4'],
            'column_map.appointment_at'  => ['nullable', 'string', 'max:4'],
            'column_map.notes'           => ['nullable', 'string', 'max:4'],

            'defaults'                   => ['required', 'array'],
            'defaults.status'            => ['nullable', Rule::in(['new', 'contacted', 'appointment_set'])],
            'defaults.acquisition_source' => ['nullable', Rule::in(Booking::ACQUISITION_SOURCES)],
            'defaults.assignee_type'     => ['nullable', Rule::in(['Clinic', 'ClinicTeamMember'])],
            'defaults.assignee_id'       => ['nullable', 'integer'],

            'service_resolutions'              => ['nullable', 'array'],
            'service_resolutions.*.name'       => ['required', 'string'],
            'service_resolutions.*.action'     => ['required', Rule::in(['map', 'create'])],
            'service_resolutions.*.service_id' => [
                'required_if:service_resolutions.*.action,map',
                'nullable', 'integer',
                Rule::exists('services', 'id')->where('clinic_id', $clinicId),
            ],
            'service_resolutions.*.sub_clinic_id' => [
                'required_if:service_resolutions.*.action,create',
                'nullable', 'integer',
                Rule::exists('sub_clinics', 'id')->where('clinic_id', $clinicId),
            ],
            'service_resolutions.*.category_ids'   => ['nullable', 'array', 'max:5'],
            'service_resolutions.*.category_ids.*' => ['integer', Rule::exists('categories', 'id')->where('is_active', true)],
        ];
    }
}
