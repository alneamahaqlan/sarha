<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('update', $this->route('systemSetting'));
    }

    public function rules(): array
    {
        // Mirrors Filament form: key is disabled/dehydrated(false) so it is never updated.
        return [
            'label'       => ['sometimes', 'required', 'string', 'max:255'],
            'type'        => ['sometimes', 'required', 'in:string,integer,decimal,boolean,json'],
            'group'       => ['nullable', 'string', 'max:255'],
            'value'       => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ];
    }
}
