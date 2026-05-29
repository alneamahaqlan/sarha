<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\ClinicReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClinicReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null && $this->user('admin')->is_active;
    }

    public function rules(): array
    {
        return [
            'status'      => ['sometimes', 'required', Rule::in(ClinicReport::STATUSES)],
            'priority'    => ['sometimes', 'required', Rule::in(ClinicReport::PRIORITIES)],
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'resolution'  => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
