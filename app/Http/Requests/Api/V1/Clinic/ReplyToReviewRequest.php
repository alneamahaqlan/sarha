<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class ReplyToReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('clinic')->check();
    }

    public function rules(): array
    {
        return [
            'reply' => ['required', 'string', 'min:2', 'max:2000'],
        ];
    }
}
