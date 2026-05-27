<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\HomepageSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHomepageSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null
            && $this->user('admin')->can('update', HomepageSection::class);
    }

    public function rules(): array
    {
        return [
            // `key` and `type` are immutable post-seed — admin tweaks presentation, not section identity.
            'title_ar'                => ['nullable', 'string', 'max:255'],
            'title_en'                => ['nullable', 'string', 'max:255'],
            'is_active'               => ['sometimes', 'boolean'],
            'item_limit'              => ['nullable', 'integer', 'min:1', 'max:50'],
            'banner_interval_seconds' => ['nullable', 'integer', 'min:2', 'max:30'],
            'show_on_mobile'          => ['sometimes', 'boolean'],
            'show_on_desktop'         => ['sometimes', 'boolean'],
            'starts_at'               => ['nullable', 'date'],
            'ends_at'                 => ['nullable', 'date', 'after_or_equal:starts_at'],
            'config'                  => ['nullable', 'array'],
            'config.category_slug'    => ['nullable', 'string', Rule::exists('categories', 'slug')->where('is_active', true)],
            'config.source'           => ['nullable', 'string', Rule::in(['featured', 'top_rated', 'best_priced'])],
            'config.min_discount'     => ['nullable', 'integer', 'min:0', 'max:90'],
            'config.only_published'   => ['nullable', 'boolean'],
            'config.interval'         => ['nullable', 'integer', 'min:2', 'max:30'],
        ];
    }
}
