<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Support\ActingClinicUser;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any role with customers.view + the explicit note-create
        // ability (reception has notes.create but not customers.manage).
        return ActingClinicUser::can('customers.view')
            && ActingClinicUser::can('customers.notes.create');
    }

    public function rules(): array
    {
        return [
            'body'      => ['required', 'string', 'max:5000'],
            'is_pinned' => ['nullable', 'boolean'],
        ];
    }
}
