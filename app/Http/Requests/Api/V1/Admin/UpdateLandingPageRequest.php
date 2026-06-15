<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\Concerns\LandingChromeRules;
use App\Models\LandingPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLandingPageRequest extends FormRequest
{
    use LandingChromeRules;

    public function authorize(): bool
    {
        $page = $this->route('landing_page');

        return $this->user('admin') !== null
            && $this->user('admin')->can('update', $page);
    }

    public function rules(): array
    {
        $id = $this->route('landing_page')->id;

        return array_merge($this->chromeRules(), [
            // type is immutable after creation (it determines the seeded block
            // set + linked entity); slug stays editable but must remain unique.
            'status'        => ['nullable', Rule::in(LandingPage::STATUSES)],
            'slug'          => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', Rule::unique('landing_pages', 'slug')->ignore($id)],
            'title_ar'      => ['nullable', 'string', 'max:255'],
            'title_en'      => ['nullable', 'string', 'max:255'],
            'internal_name' => ['nullable', 'string', 'max:255'],

            'clinic_id'   => ['nullable', 'exists:clinics,id'],
            'offer_id'    => ['nullable', 'exists:offers,id'],
            'city_id'     => ['nullable', 'exists:cities,id'],
            'category_id' => ['nullable', 'exists:categories,id'],

            'comparison_clinic_ids'   => ['nullable', 'array', 'min:2'],
            'comparison_clinic_ids.*' => ['integer', 'exists:clinics,id'],

            'cover_image'  => ['nullable', 'string', 'max:1000'],
            'social_image' => ['nullable', 'string', 'max:1000'],

            'published_at' => ['nullable', 'date'],
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
