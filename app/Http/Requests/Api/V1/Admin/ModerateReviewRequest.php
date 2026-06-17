<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin moderation of a reported review. `hide` removes it from public
 * view (is_visible=false) and REQUIRES a reason for the audit trail —
 * permitted ONLY for spam/abuse, never for a genuine negative. `dismiss`
 * keeps the review public (the report was unfounded).
 */
class ModerateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['hide', 'dismiss'])],
            // Mandatory justification when hiding (audit); optional on dismiss.
            'reason' => ['nullable', 'required_if:action,hide', 'string', 'min:3', 'max:1000'],
        ];
    }
}
