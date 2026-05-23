<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null && $this->user('admin')->is_active;
    }

    public function rules(): array
    {
        return [
            // Mirrors Filament BulkActionGroup: Delete / ForceDelete / Restore.
            'action' => ['required', 'in:delete,restore,force_delete'],
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['integer'],
        ];
    }
}
