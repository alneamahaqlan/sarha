<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('create', \App\Models\Admin::class);
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255', 'unique:admins,email'],
            'password'  => ['required', 'confirmed', Password::min(8)],
            'role'      => ['required', 'in:super_admin,admin,sales'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
