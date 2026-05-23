<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        $id = $this->route('user')?->id;

        return [
            'name'      => ['sometimes', 'required', 'string', 'max:255'],
            'phone'     => ['sometimes', 'required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($id)],
            'email'     => ['nullable', 'email', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
