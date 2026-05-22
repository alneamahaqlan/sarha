<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;

class GenerateArticleAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        // Mirrors Filament ai_excerpt / ai_article actions: title required.
        return [
            'kind'  => ['required', 'in:excerpt,article'],
            'title' => ['required', 'string', 'max:255'],
        ];
    }
}
