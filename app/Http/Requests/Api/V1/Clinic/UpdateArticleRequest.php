<?php

namespace App\Http\Requests\Api\V1\Clinic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('clinic') !== null
            && $this->user('clinic')->can('update', $this->route('article'));
    }

    public function rules(): array
    {
        $id = $this->route('article')?->id;

        return [
            'title'            => ['sometimes', 'required', 'string', 'max:255'],
            'slug'             => ['sometimes', 'required', 'string', 'max:255', Rule::unique('articles', 'slug')->ignore($id)],
            'body'             => ['sometimes', 'required', 'string'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'cover_image'      => ['nullable', 'string'],
            'tags'             => ['nullable', 'array'],
            'tags.*'           => ['string'],
            'is_published'     => ['nullable', 'boolean'],
        ];
    }
}
