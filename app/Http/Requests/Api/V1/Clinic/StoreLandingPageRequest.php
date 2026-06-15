<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Http\Requests\Api\V1\Concerns\LandingChromeRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A complex creates a landing page for ITSELF. The type is forced to `clinic`
 * and the page is linked to the authenticated complex in the controller, so the
 * clinic never supplies `type`, the linked-entity ids, `status`, or any of the
 * approval fields — those are server-controlled. Content/SEO/CTA/chrome are the
 * same surface the admin editor exposes (minus page-type selection).
 */
class StoreLandingPageRequest extends FormRequest
{
    use LandingChromeRules;

    public function authorize(): bool
    {
        return $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        return array_merge($this->chromeRules(), [
            // Slug is optional — the controller derives one from the complex
            // name when omitted. When supplied it must be a clean, unique slug.
            'slug'          => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', 'unique:landing_pages,slug'],
            'title_ar'      => ['nullable', 'string', 'max:255'],
            'title_en'      => ['nullable', 'string', 'max:255'],
            'internal_name' => ['nullable', 'string', 'max:255'],

            'cover_image'  => ['nullable', 'string', 'max:1000'],
            'social_image' => ['nullable', 'string', 'max:1000'],

            'starts_at'    => ['nullable', 'date'],
            'ends_at'      => ['nullable', 'date', 'after_or_equal:starts_at'],

            'cta_label_ar'     => ['nullable', 'string', 'max:255'],
            'cta_label_en'     => ['nullable', 'string', 'max:255'],
            'cta_url'          => ['nullable', 'string', 'max:1000', 'url'],
            'cta_style'        => ['nullable', 'string', Rule::in(['book', 'link', 'scroll_booking'])],
            'whatsapp_phone'   => ['nullable', 'string', 'max:20'],
            'call_phone'       => ['nullable', 'string', 'max:20'],
            'whatsapp_enabled' => ['nullable', 'boolean'],
            'call_enabled'     => ['nullable', 'boolean'],

            'seo_title_ar'       => ['nullable', 'string', 'max:255'],
            'seo_title_en'       => ['nullable', 'string', 'max:255'],
            'seo_description_ar' => ['nullable', 'string', 'max:300'],
            'seo_description_en' => ['nullable', 'string', 'max:300'],
            'seo_keywords'       => ['nullable', 'string', 'max:500'],
            'canonical_url'      => ['nullable', 'string', 'max:1000', 'url'],
            'meta_robots'        => ['nullable', 'string', 'max:100'],
            'in_sitemap'         => ['nullable', 'boolean'],

            'og_title_ar'       => ['nullable', 'string', 'max:255'],
            'og_title_en'       => ['nullable', 'string', 'max:255'],
            'og_description_ar' => ['nullable', 'string', 'max:300'],
            'og_description_en' => ['nullable', 'string', 'max:300'],

            'schema_markup' => ['nullable', 'array'],
            'schema_type'   => ['nullable', 'string', 'max:100'],
        ]);
    }
}
