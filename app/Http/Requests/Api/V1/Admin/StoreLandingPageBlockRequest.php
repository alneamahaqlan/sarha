<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\LandingPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLandingPageBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $page = $this->route('landing_page');

        return $this->user('admin') !== null
            && $this->user('admin')->can('update', $page);
    }

    public function rules(): array
    {
        return [
            'type'       => ['required', Rule::in(LandingPage::BLOCK_TYPES)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_visible' => ['nullable', 'boolean'],
            'config'     => ['nullable', 'array'],
        ];
    }
}
