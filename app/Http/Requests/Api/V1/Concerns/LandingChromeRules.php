<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Models\LandingPage;
use Illuminate\Validation\Rule;

/**
 * Validation rules for a landing page's per-page header/footer override.
 * Shared by the admin AND clinic Store/Update requests. Colors are constrained
 * to a hex pattern so they can be dropped into a `style` attribute without CSS
 * injection.
 */
trait LandingChromeRules
{
    protected function chromeRules(): array
    {
        $hex = ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'];

        return [
            'header_mode' => ['nullable', Rule::in(LandingPage::HEADER_CHROME_MODES)],
            'footer_mode' => ['nullable', Rule::in(LandingPage::CHROME_MODES)],

            // Header config (consumed only when header_mode is minimal/custom).
            'header_config'                 => ['nullable', 'array'],
            'header_config.logo_image'      => ['nullable', 'string', 'max:1000'],
            'header_config.show_language'   => ['nullable', 'boolean'],
            'header_config.sticky'          => ['nullable', 'boolean'],
            'header_config.bg_color'        => $hex,
            'header_config.text_color'      => $hex,
            'header_config.cta_label'       => ['nullable', 'string', 'max:80'],
            'header_config.cta_url'         => ['nullable', 'string', 'max:1000'],
            'header_config.links'           => ['nullable', 'array', 'max:12'],
            'header_config.links.*.label'   => ['required_with:header_config.links.*', 'string', 'max:80'],
            'header_config.links.*.url'     => ['required_with:header_config.links.*', 'string', 'max:1000'],
            'header_config.links.*.new_tab' => ['nullable', 'boolean'],

            // Footer config (consumed only when footer_mode is minimal/custom).
            'footer_config'                 => ['nullable', 'array'],
            'footer_config.logo_image'      => ['nullable', 'string', 'max:1000'],
            'footer_config.about'           => ['nullable', 'string', 'max:600'],
            'footer_config.copyright'       => ['nullable', 'string', 'max:300'],
            'footer_config.phone'           => ['nullable', 'string', 'max:40'],
            'footer_config.email'           => ['nullable', 'string', 'max:120'],
            'footer_config.whatsapp'        => ['nullable', 'string', 'max:40'],
            'footer_config.bg_color'        => $hex,
            'footer_config.text_color'      => $hex,
            'footer_config.social'          => ['nullable', 'array'],
            'footer_config.social.instagram'=> ['nullable', 'string', 'max:1000'],
            'footer_config.social.twitter'  => ['nullable', 'string', 'max:1000'],
            'footer_config.social.snapchat' => ['nullable', 'string', 'max:1000'],
            'footer_config.social.tiktok'   => ['nullable', 'string', 'max:1000'],
            'footer_config.links'           => ['nullable', 'array', 'max:12'],
            'footer_config.links.*.label'   => ['required_with:footer_config.links.*', 'string', 'max:80'],
            'footer_config.links.*.url'     => ['required_with:footer_config.links.*', 'string', 'max:1000'],
            'footer_config.links.*.new_tab' => ['nullable', 'boolean'],
        ];
    }
}
