<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLandingPageBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $block = $this->route('block');

        return $this->user('admin') !== null
            && $block !== null
            && $this->user('admin')->can('update', $block->landingPage);
    }

    public function rules(): array
    {
        return [
            'is_visible' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'config'     => ['nullable', 'array'],
        ];
    }
}
