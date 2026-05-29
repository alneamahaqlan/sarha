<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Models\ClinicReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        return [
            'type'        => ['required', Rule::in(ClinicReport::TYPES)],
            'priority'    => ['nullable', Rule::in(ClinicReport::PRIORITIES)],
            'subject'     => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
