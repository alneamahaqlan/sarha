<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Http\Requests\Api\V1\Concerns\LandingChromeRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edit a clinic-owned landing page. Same content surface as the store request;
 * the slug stays unique but ignores the current row. Ownership is enforced in
 * the controller (route-model binding + ownedOrFail).
 */
class UpdateLandingPageRequest extends FormRequest
{
    use LandingChromeRules;

    public function authorize(): bool
    {
        return $this->user('clinic') !== null;
    }

    public function rules(): array
    {
        $id = $this->route('landing_page')?->id;

        return array_merge($this->chromeRules(), [
            'slug'          => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', Rule::unique('landing_pages', 'slug')->ignore($id)],
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
