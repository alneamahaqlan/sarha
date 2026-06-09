<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Executes the import: re-reads the sheet server-side, resolves every
 * service name (auto-matched, mapped to an existing service, or created as
 * a new pending one), creates the bookings, and records the run. Optionally
 * persists the source for re-pulls.
 */
class ImportCommitRequest extends FormRequest
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
            'sheet_url'                  => ['required', 'string', 'max:2048'],
            'row_from'                   => ['required', 'integer', 'min:1'],
            'row_to'                     => ['required', 'integer', 'gte:row_from'],
            'column_map'                 => ['required', 'array'],
            'column_map.customer_name'   => ['required', 'string', 'max:4'],
            'column_map.customer_phone'  => ['required', 'string', 'max:4'],
            'column_map.service'         => ['nullable', 'string', 'max:4'],
            'column_map.appointment_at'  => ['nullable', 'string', 'max:4'],
            'column_map.notes'           => ['nullable', 'string', 'max:4'],

            // Unified per-import values applied to every created booking.
            'defaults'                   => ['required', 'array'],
            'defaults.status'            => ['nullable', Rule::in(['new', 'contacted', 'appointment_set'])],
            'defaults.acquisition_source' => ['nullable', Rule::in(Booking::ACQUISITION_SOURCES)],
            'defaults.assignee_type'     => ['nullable', Rule::in(['Clinic', 'ClinicTeamMember'])],
            'defaults.assignee_id'       => ['nullable', 'integer'],

            // One resolution per distinct unmatched service name. Exact
            // matches are resolved server-side and need no entry here.
            'service_resolutions'              => ['nullable', 'array'],
            'service_resolutions.*.name'       => ['required', 'string'],
            'service_resolutions.*.action'     => ['required', Rule::in(['map', 'create'])],
            // action=map → existing clinic service.
            'service_resolutions.*.service_id' => [
                'required_if:service_resolutions.*.action,map',
                'nullable', 'integer',
                Rule::exists('services', 'id')->where('clinic_id', $clinicId),
            ],
            // action=create → must pick the sub-clinic (every service belongs
            // to one) and at least one specialty.
            'service_resolutions.*.sub_clinic_id' => [
                'required_if:service_resolutions.*.action,create',
                'nullable', 'integer',
                Rule::exists('sub_clinics', 'id')->where('clinic_id', $clinicId),
            ],
            // Optional: when omitted on a create, the service inherits the
            // chosen sub-clinic's specialty automatically.
            'service_resolutions.*.category_ids'   => ['nullable', 'array', 'max:5'],
            'service_resolutions.*.category_ids.*' => ['integer', Rule::exists('categories', 'id')->where('is_active', true)],

            // Persist the source for easy re-pulls (campaign sheets).
            'save_source'                => ['nullable', 'boolean'],
            'source_name'                => ['required_if:save_source,true', 'nullable', 'string', 'max:255'],
            'import_source_id'           => ['nullable', 'integer', Rule::exists('clinic_import_sources', 'id')->where('clinic_id', $clinicId)],
        ];
    }
}
