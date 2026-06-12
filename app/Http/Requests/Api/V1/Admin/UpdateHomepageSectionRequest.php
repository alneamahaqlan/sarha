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

            // `faqs` type: up to FAQ_LIMIT { question, answer } rows authored
            // by the admin and rendered as an accordion on the landing page.
            'config.faqs'             => ['nullable', 'array', 'max:'.HomepageSection::FAQ_LIMIT],
            'config.faqs.*.question'  => ['required_with:config.faqs.*.answer', 'nullable', 'string', 'max:255'],
            'config.faqs.*.answer'    => ['required_with:config.faqs.*.question', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Strip blank FAQ rows before validation so the admin can leave unused
     * slots empty without tripping required_with, and we never persist empty
     * placeholders the public page would render.
     */
    protected function prepareForValidation(): void
    {
        $config = $this->input('config');
        if (! is_array($config) || ! isset($config['faqs']) || ! is_array($config['faqs'])) {
            return;
        }

        $config['faqs'] = collect($config['faqs'])
            ->map(fn ($row) => [
                'question' => is_array($row) ? trim((string) ($row['question'] ?? '')) : '',
                'answer'   => is_array($row) ? trim((string) ($row['answer'] ?? '')) : '',
            ])
            ->filter(fn ($row) => $row['question'] !== '' || $row['answer'] !== '')
            ->values()
            ->all();

        $this->merge(['config' => $config]);
    }
}
