<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('update', $this->route('admin_user'));
    }

    public function rules(): array
    {
        $id = $this->route('admin_user')?->id;

        return [
            'name'      => ['sometimes', 'required', 'string', 'max:255'],
            'email'     => ['sometimes', 'required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($id)],
            'password'  => ['nullable', 'confirmed', Password::min(8)],
            'role'      => ['sometimes', 'required', 'in:super_admin,admin,sales'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
