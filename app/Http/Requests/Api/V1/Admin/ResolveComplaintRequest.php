<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ResolveComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('resolve', $this->route('complaint'));
    }

    public function rules(): array
    {
        // Mirrors Filament resolve form: Textarea('resolution')->required()->rows(4).
        return [
            'resolution' => ['required', 'string', 'min:1'],
        ];
    }
}
