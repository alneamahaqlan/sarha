<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            'clinic_id'        => ['required', 'integer', 'exists:clinics,id'],
            'title'            => ['required', 'string', 'max:255'],
            'slug'             => ['nullable', 'string', 'max:255', 'unique:articles,slug'],
            'body'             => ['required', 'string'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'cover_image'      => ['nullable', 'string'],
            'tags'             => ['nullable', 'array'],
            'tags.*'           => ['string'],
            'is_published'     => ['nullable', 'boolean'],
            'ai_generated'     => ['nullable', 'boolean'],
        ];
    }
}
