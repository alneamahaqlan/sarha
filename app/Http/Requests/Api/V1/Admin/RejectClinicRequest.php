<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('reject', $this->route('clinic'));
    }

    public function rules(): array
    {
        // Mirrors Filament form: Textarea('rejection_reason')->required()->rows(3).
        return [
            'rejection_reason' => ['required', 'string', 'min:1'],
        ];
    }
}
