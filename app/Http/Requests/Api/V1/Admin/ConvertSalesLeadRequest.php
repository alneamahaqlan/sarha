<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ConvertSalesLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('convert', $this->route('salesLead'));
    }

    public function rules(): array
    {
        return [
            'plan' => ['required', 'in:basic,premium'],
        ];
    }
}
