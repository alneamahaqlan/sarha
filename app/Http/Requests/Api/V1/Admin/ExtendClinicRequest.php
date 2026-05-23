<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ExtendClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('extend', $this->route('clinic'));
    }

    public function rules(): array
    {
        // Filament only exposes 30/90 buttons. Server stays strict to the same set.
        return [
            'days' => ['required', 'integer', 'in:30,90'],
        ];
    }
}
