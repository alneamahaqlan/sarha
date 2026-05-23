<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        $id = $this->route('article')?->id;

        return [
            'clinic_id'        => ['sometimes', 'integer', 'exists:clinics,id'],
            'title'            => ['sometimes', 'required', 'string', 'max:255'],
            'slug'             => ['sometimes', 'required', 'string', 'max:255', Rule::unique('articles', 'slug')->ignore($id)],
            'body'             => ['sometimes', 'required', 'string'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'cover_image'      => ['nullable', 'string'],
            'tags'             => ['nullable', 'array'],
            'tags.*'           => ['string'],
            'is_published'     => ['nullable', 'boolean'],
            'ai_generated'     => ['nullable', 'boolean'],
        ];
    }
}
