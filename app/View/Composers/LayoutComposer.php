<?php

namespace App\View\Composers;

use App\Models\NavigationLink;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Feeds the public header/footer partials with admin-managed navigation links
 * and footer contact/social settings. Links are cached per location (busted by
 * the NavigationLink model on save); settings are individually cached by
 * SystemSetting::get.
 */
class LayoutComposer
{
    public function compose(View $view): void
    {
        $view->with([
            'headerLinks'    => $this->headerLinks(),
            'footerColumns'  => $this->footerColumns(),
            'footerSettings' => $this->footerSettings(),
        ]);
    }

    private function headerLinks()
    {
        return Cache::remember(NavigationLink::CACHE_HEADER, 3600, function () {
            return NavigationLink::with('staticPage')->activeFor('header')->get();
        });
    }

    /** Footer links grouped by column (1-3). */
    private function footerColumns()
    {
        return Cache::remember(NavigationLink::CACHE_FOOTER, 3600, function () {
            return NavigationLink::with('staticPage')->activeFor('footer')
                ->get()
                ->groupBy(fn ($link) => $link->footer_column ?: 1)
                ->sortKeys();
        });
    }

    private function footerSettings(): array
    {
        $locale = app()->getLocale();

        return [
            'about'     => SystemSetting::get($locale === 'en' ? 'footer_about_en' : 'footer_about_ar')
                ?: SystemSetting::get('footer_about_ar'),
            'phone'     => SystemSetting::get('platform_phone'),
            'email'     => SystemSetting::get('platform_email'),
            'whatsapp'  => SystemSetting::get('platform_whatsapp'),
            'instagram' => SystemSetting::get('social_instagram'),
            'twitter'   => SystemSetting::get('social_twitter'),
            'snapchat'  => SystemSetting::get('social_snapchat'),
            'tiktok'    => SystemSetting::get('social_tiktok'),
        ];
    }
}
