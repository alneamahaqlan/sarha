{{--
    "عدد الظهور" social-proof badge for a complex.

    Shows the complex's total impressions (search + listing + similar +
    profile/service/offer/doctor page opens). Rendered on the complex page
    and on anything that belongs to it, so visitors get a consistent signal
    of how active the complex is.

    Super-admin controlled (System Settings → group "display"):
      - public_impressions_badge_enabled : master on/off switch.
      - public_impressions_badge_min     : hide below this many impressions,
        so freshly-onboarded complexes don't broadcast a tiny number.
    Both fall back to the Clinic constants when the setting row is absent.

    Required: $clinic — the complex whose figure to show.
    Optional: $size   — 'sm' (default, inline with header badges) | 'md'.
--}}
@php
    $badgeEnabled = (bool) \App\Models\SystemSetting::get('public_impressions_badge_enabled', true);
    $badgeMin = (int) \App\Models\SystemSetting::get('public_impressions_badge_min', \App\Models\Clinic::IMPRESSIONS_BADGE_MIN);
    $impressions = $badgeEnabled ? $clinic->impressionsCount() : 0;
    $size = $size ?? 'sm';
    $pad = $size === 'md' ? 'px-3 py-1 text-sm' : 'px-2.5 py-0.5 text-xs';
    $iconSize = $size === 'md' ? 'w-4 h-4' : 'w-3.5 h-3.5';
@endphp
@if($badgeEnabled && $impressions >= $badgeMin)
    <span class="inline-flex items-center gap-1.5 bg-sage-50 text-sage-700 ring-1 ring-sage-100 rounded-full font-semibold {{ $pad }}"
          title="{{ __('site.impressions_tooltip') }}">
        <x-icon name="eye" class="{{ $iconSize }} text-sage-500" />
        <span class="tabular-nums tracking-tight">{{ number_format($impressions) }}</span>
        <span class="font-normal text-sage-600">@lang('site.impressions_label')</span>
    </span>
@endif
