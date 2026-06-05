<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SalesLead;
use App\Models\SalesLeadActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesLeadActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Logging an activity is an update to the lead's record.
        return $this->user('admin') !== null
            && $this->user('admin')->can('update', $this->route('salesLead'));
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(SalesLeadActivity::MANUAL_TYPES)],
            'body' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
