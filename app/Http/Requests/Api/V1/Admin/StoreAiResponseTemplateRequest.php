<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAiResponseTemplateRequest extends FormRequest
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
            'label'      => ['required', 'string', 'max:255'],
            'content'    => ['required', 'string', 'max:4000'],
            'is_active'  => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
