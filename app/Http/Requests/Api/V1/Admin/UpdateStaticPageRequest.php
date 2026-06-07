<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaticPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('update', $this->route('static_page'));
    }

    public function rules(): array
    {
        $page = $this->route('static_page');
        $id = $page?->id;

        return [
            // System pages keep their slug locked (it's wired into routes/seed).
            'slug'                => [
                'sometimes', 'required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('static_pages', 'slug')->ignore($id),
                Rule::prohibitedIf((bool) ($page?->is_system) && $this->input('slug') !== $page?->slug),
            ],
            'title_ar'            => ['sometimes', 'required', 'string', 'max:255'],
            'title_en'            => ['nullable', 'string', 'max:255'],
            'body_ar'             => ['nullable', 'string'],
            'body_en'             => ['nullable', 'string'],
            'meta_description_ar' => ['nullable', 'string', 'max:300'],
            'meta_description_en' => ['nullable', 'string', 'max:300'],
            'is_active'           => ['nullable', 'boolean'],
            'published_at'        => ['nullable', 'date'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
        ];
    }
}
