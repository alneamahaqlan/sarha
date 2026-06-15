@php
    /**
     * Per-landing-page header chrome. $lp = ['mode' => ..., 'config' => [...]].
     *   default  → the platform's global header (unchanged).
     *   none     → no header at all (distraction-free).
     *   minimal  → centered logo only (custom logo image optional).
     *   custom   → own logo + nav links + CTA + colors + optional language toggle.
     *
     * Everything is admin-authored, but we still block `javascript:`/`data:`
     * hrefs defensively and constrain colors to the validated hex pattern.
     */
    $mode = $lp['mode'] ?? 'default';
    $cfg  = $lp['config'] ?? [];

    $safeUrl = function (?string $url): string {
        $url = trim((string) $url);
        if ($url === '') return '#';
        return \Illuminate\Support\Str::startsWith(\Illuminate\Support\Str::lower($url), ['javascript:', 'data:', 'vbscript:'])
            ? '#' : $url;
    };
    $hex = fn (?string $c) => is_string($c) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $c) ? $c : null;

    $logoImage = !empty($cfg['logo_image']) ? \Illuminate\Support\Facades\Storage::url($cfg['logo_image']) : null;
@endphp

@if($mode === 'clinic' && !empty($clinic))
    {{-- Adopt the linked complex's full public-profile hero as the header
         section: logo + stories + follow + stats + specialties + social, plus a
         "visit the clinic page" link. $clinic comes from the landing view-model
         (set for clinic/offer/custom-with-clinic page types). --}}
    @include('public.partials.clinic-hero-scripts')
    <div class="max-w-6xl mx-auto px-4 pt-6">
        @include('public.partials.clinic-hero', ['clinic' => $clinic, 'showVisit' => true])
    </div>
@elseif($mode === 'clinic')
    {{-- clinic mode selected but this page has no linked complex → platform header --}}
    @include('layouts.partials.header')
@elseif($mode === 'default')
    @include('layouts.partials.header')
@elseif($mode === 'none')
    {{-- header intentionally hidden --}}
@elseif($mode === 'minimal')
    @php $sticky = (bool) ($cfg['sticky'] ?? false); @endphp
    <header class="bg-white shadow-sm {{ $sticky ? 'sticky top-0 z-50' : '' }}"
            @if($hex($cfg['bg_color'] ?? null)) style="background-color: {{ $hex($cfg['bg_color']) }}" @endif>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-center">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                @if($logoImage)
                    <img src="{{ $logoImage }}" alt="{{ $page->title ?? __('site.brand') }}" class="h-10 w-auto object-contain">
                @else
                    <x-logo :size="40" />
                @endif
            </a>
        </div>
    </header>
@elseif($mode === 'custom')
    @php
        $bg      = $hex($cfg['bg_color'] ?? null);
        $text    = $hex($cfg['text_color'] ?? null);
        $links   = collect($cfg['links'] ?? [])->filter(fn ($l) => filled($l['label'] ?? null));
        $sticky  = (bool) ($cfg['sticky'] ?? false);
        $showLang = (bool) ($cfg['show_language'] ?? false);
        $altLocale = app()->getLocale() === 'ar' ? 'en' : 'ar';
        $hasCta  = filled($cfg['cta_label'] ?? null);
    @endphp
    <header class="shadow-sm {{ $sticky ? 'sticky top-0 z-50' : '' }} {{ $bg ? '' : 'bg-white' }}"
            x-data="{ open: false }"
            @style([ "background-color: {$bg}" => $bg, "color: {$text}" => $text ])>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-2">
                    @if($links->isNotEmpty())
                        <button type="button" @click="open = !open"
                                class="md:hidden inline-flex items-center justify-center min-h-touch min-w-touch -ms-2"
                                :aria-expanded="open.toString()" aria-label="@lang('site.menu')">
                            <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                            <svg x-show="open" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    @endif
                    <a href="{{ route('home') }}" class="flex items-center gap-2">
                        @if($logoImage)
                            <img src="{{ $logoImage }}" alt="{{ $page->title ?? __('site.brand') }}" class="h-10 w-auto object-contain">
                        @else
                            <x-logo :size="40" />
                        @endif
                    </a>
                </div>

                <div class="hidden md:flex items-center gap-6">
                    @foreach($links as $link)
                        <a href="{{ $safeUrl($link['url'] ?? '#') }}"
                           @if(!empty($link['new_tab'])) target="_blank" rel="noopener" @endif
                           class="inline-flex items-center min-h-touch hover:opacity-70 transition-opacity">{{ $link['label'] }}</a>
                    @endforeach
                </div>

                <div class="flex items-center gap-3">
                    @if($showLang)
                        <a href="{{ route('lang.switch', $altLocale) }}"
                           class="inline-flex items-center justify-center min-h-touch text-sm px-3 border border-current/20 rounded-lg hover:opacity-70"
                           title="@lang('site.language')">{{ $altLocale === 'en' ? 'English' : 'العربية' }}</a>
                    @endif
                    @if($hasCta)
                        <a href="{{ $safeUrl($cfg['cta_url'] ?? '#') }}"
                           class="inline-flex items-center justify-center min-h-touch bg-sage-600 text-white px-4 rounded-lg text-sm hover:bg-sage-700 transition-colors">
                            {{ $cfg['cta_label'] }}
                        </a>
                    @endif
                </div>
            </div>

            @if($links->isNotEmpty())
                <div x-show="open" x-cloak @click.outside="open = false" class="md:hidden border-t border-current/10 py-2">
                    @foreach($links as $link)
                        <a href="{{ $safeUrl($link['url'] ?? '#') }}"
                           @if(!empty($link['new_tab'])) target="_blank" rel="noopener" @endif
                           class="flex items-center min-h-touch px-2 hover:opacity-70">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </header>
@endif
