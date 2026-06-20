<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Models\VerifiedReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A clinic flags a review as spam/abuse for admin review. Note the
 * reasons: spam/abuse/fake/other — a negative review is NOT a reportable
 * reason, and reporting never hides the review on its own.
 */
class ReportReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('clinic')->check();
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(VerifiedReview::REPORT_REASONS)],
            'note'   => ['nullable', 'string', 'max:500'],
        ];
    }
}
