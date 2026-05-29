<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiRestrictionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = $this->user('admin');
        return $admin !== null
            && (method_exists($admin, 'isSuperAdmin') ? $admin->isSuperAdmin() : true);
    }

    public function rules(): array
    {
        return [
            // `type` and `value` are immutable post-create — admin can
            // toggle is_active or rewrite the override copy, but
            // changing the identity of a rule re-shuffles uniqueness.
            'response_override' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active'         => ['sometimes', 'boolean'],
        ];
    }
}
