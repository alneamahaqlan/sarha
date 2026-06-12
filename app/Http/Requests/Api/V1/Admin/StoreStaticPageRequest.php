<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\StaticPage;
use Illuminate\Foundation\Http\FormRequest;

class StoreStaticPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('create', StaticPage::class);
    }

    public function rules(): array
    {
        return [
            'slug'                => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', 'unique:static_pages,slug'],
            'title_ar'            => ['required', 'string', 'max:255'],
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
