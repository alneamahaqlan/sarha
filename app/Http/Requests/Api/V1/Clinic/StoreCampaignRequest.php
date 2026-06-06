<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Support\ActingClinicUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ActingClinicUser::can('campaigns.manage');
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:120'],
            // The message body; {{name}} is rendered per recipient client-side.
            'message_template' => ['required', 'string', 'max:2000'],

            'audience'                    => ['nullable', 'array'],
            'audience.segment'            => ['nullable', Rule::in(['vip', 'repeat', 'new', 'new_month', 'has_complaint', 'inactive_90d'])],
            'audience.booking_range'      => ['nullable', Rule::in(['1', '2-4', '5-9', '10+'])],
            'audience.last_interaction'   => ['nullable', Rule::in(['30d', '90d', '180d_plus'])],
            'audience.has_notes'          => ['nullable', 'boolean'],
            'audience.search'             => ['nullable', 'string', 'max:120'],
        ];
    }
}
