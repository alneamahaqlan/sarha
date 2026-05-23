<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('reject', $this->route('complaint'));
    }

    public function rules(): array
    {
        // Mirrors Filament reject form: Textarea('admin_notes')->required()->rows(3).
        return [
            'admin_notes' => ['required', 'string', 'min:1'],
        ];
    }
}
