{{-- Landing hero. Pulls cover image + title from the page; copy overridable via block config. --}}
@php
    $loc = app()->getLocale();
    $heading = $cfg['heading'] ?? ($loc === 'en' ? $page->title_en : $page->title_ar) ?: $page->title_ar;
    $sub = $cfg['subheading'] ?? null;
    $showCta = $cfg['show_cta'] ?? true;
    $cover = $page->cover_image ? \Illuminate\Support\Facades\Storage::url($page->cover_image) : null;
    $ctaLabel = ($loc === 'en' ? $page->cta_label_en : $page->cta_label_ar) ?: __('site.lp_book_now');
@endphp

<section class="relative overflow-hidden">
    @if($cover)
        <img src="{{ $cover }}" alt="{{ $heading }}" class="absolute inset-0 h-full w-full object-cover" loading="eager">
        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/40 to-black/20"></div>
    @else
        <div class="absolute inset-0 bg-gradient-to-br from-sage-700 to-sage-900"></div>
    @endif

    <div class="relative max-w-5xl mx-auto px-4 py-20 sm:py-28 text-center text-white">
        <h1 class="text-3xl sm:text-5xl font-bold leading-tight drop-shadow">{{ $heading }}</h1>
        @if($sub)
            <p class="mt-4 text-lg sm:text-xl text-white/90 max-w-2xl mx-auto">{{ $sub }}</p>
        @endif

        @if($showCta)
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                @if($page->cta_style === 'link' && $page->cta_url)
                    <a href="{{ $page->cta_url }}" data-lp-track="click" data-lp-button="hero_cta"
                       class="inline-flex items-center gap-2 bg-white text-sage-800 font-semibold px-6 py-3 rounded-full shadow-lg hover:bg-sage-50 transition">
                        {{ $ctaLabel }}
                    </a>
                @else
                    <a href="#lp-booking" data-lp-track="click" data-lp-button="hero_cta"
                       class="inline-flex items-center gap-2 bg-white text-sage-800 font-semibold px-6 py-3 rounded-full shadow-lg hover:bg-sage-50 transition">
                        {{ $ctaLabel }}
                    </a>
                @endif

                @if($page->whatsapp_enabled && $page->whatsapp_phone)
                    <a href="https://wa.me/{{ preg_replace('/\D/', '', $page->whatsapp_phone) }}" target="_blank" rel="noopener"
                       data-lp-track="whatsapp"
                       class="inline-flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white font-semibold px-6 py-3 rounded-full shadow-lg transition">
                        @lang('site.whatsapp_label')
                    </a>
                @endif
                @if($page->call_enabled && $page->call_phone)
                    <a href="tel:{{ $page->call_phone }}" data-lp-track="call"
                       class="inline-flex items-center gap-2 bg-white/20 backdrop-blur text-white font-semibold px-6 py-3 rounded-full ring-1 ring-white/40 hover:bg-white/30 transition">
                        @lang('site.lp_call_label')
                    </a>
                @endif
            </div>
        @endif
    </div>
</section>
