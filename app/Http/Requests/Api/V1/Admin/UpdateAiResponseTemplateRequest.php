<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiResponseTemplateRequest extends FormRequest
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
            'label'      => ['sometimes', 'required', 'string', 'max:255'],
            'content'    => ['sometimes', 'required', 'string', 'max:4000'],
            'is_active'  => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
