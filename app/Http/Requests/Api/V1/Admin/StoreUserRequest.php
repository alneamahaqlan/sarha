<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('create', \App\Models\User::class);
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'phone'     => ['required', 'string', 'max:20', 'unique:users,phone'],
            'email'     => ['nullable', 'email', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
