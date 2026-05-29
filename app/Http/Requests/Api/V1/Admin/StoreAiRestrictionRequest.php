<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\AiRestriction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAiRestrictionRequest extends FormRequest
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
            'type'              => ['required', Rule::in(AiRestriction::TYPES)],
            'value'             => ['required', 'string', 'max:255'],
            'response_override' => ['nullable', 'string', 'max:2000'],
            'is_active'         => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $type = $this->input('type');
            $value = $this->input('value');

            // For blocklists, the `value` must be a valid id pointing to
            // an active row — catches the "I copy-pasted the clinic name"
            // mistake at admin time instead of at chat time.
            if ($type === AiRestriction::TYPE_CLINIC_BLOCKLIST) {
                $exists = is_numeric($value)
                    && \App\Models\Clinic::where('id', (int) $value)->exists();
                if (! $exists) {
                    $v->errors()->add('value', __('ai_center.clinic_id_invalid'));
                }
            }
            if ($type === AiRestriction::TYPE_CATEGORY_BLOCKLIST) {
                $exists = is_numeric($value)
                    && \App\Models\Category::where('id', (int) $value)->exists();
                if (! $exists) {
                    $v->errors()->add('value', __('ai_center.category_id_invalid'));
                }
            }

            // Uniqueness — admin can't double-add the same rule.
            if (AiRestriction::where('type', $type)->where('value', (string) $value)->exists()) {
                $v->errors()->add('value', __('ai_center.duplicate_restriction'));
            }
        });
    }
}
