<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Models\ClinicPageSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePageSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        return [
            // `key` is immutable — admin only tweaks presentation, not the
            // section's identity (which drives which partial renders).
            'is_active'  => ['sometimes', 'boolean'],
            'title_ar'   => ['nullable', 'string', 'max:255'],
            'title_en'   => ['nullable', 'string', 'max:255'],
            'item_limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // Protected sections can't be hidden. Enforced here (not as a model
        // event) so the user gets a 422 with a usable error message rather
        // than a silent write that gets clobbered later.
        $validator->after(function (Validator $v) {
            $section = $this->route('section');
            if (! $section instanceof ClinicPageSection) {
                return;
            }
            if ($this->has('is_active')
                && $this->boolean('is_active') === false
                && in_array($section->key, ClinicPageSection::PROTECTED_KEYS, true)) {
                $v->errors()->add('is_active', __('clinic_page_builder.cannot_hide_protected'));
            }
        });
    }
}
