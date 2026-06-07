@php
    // Column headings keyed by footer_column (1-3). Falls back to "quick links".
    $colHeadings = [
        1 => __('site.footer_quick_links'),
        2 => __('site.footer_company'),
        3 => __('site.footer_legal'),
    ];

    // Branded social glyphs — only rendered when the matching setting is set.
    $socialBrands = [
        'instagram' => ['label' => __('site.social_instagram'), 'svg' => '<path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16Zm0 1.62c-3.15 0-3.52.01-4.76.07-1.15.05-1.77.24-2.19.4-.55.22-.94.47-1.35.88-.41.41-.66.8-.88 1.35-.16.42-.35 1.04-.4 2.19-.06 1.24-.07 1.61-.07 4.76s.01 3.52.07 4.76c.05 1.15.24 1.77.4 2.19.22.55.47.94.88 1.35.41.41.8.66 1.35.88.42.16 1.04.35 2.19.4 1.24.06 1.61.07 4.76.07s3.52-.01 4.76-.07c1.15-.05 1.77-.24 2.19-.4.55-.22.94-.47 1.35-.88.41-.41.66-.8.88-1.35.16-.42.35-1.04.4-2.19.06-1.24.07-1.61.07-4.76s-.01-3.52-.07-4.76c-.05-1.15-.24-1.77-.4-2.19a3.64 3.64 0 0 0-.88-1.35 3.64 3.64 0 0 0-1.35-.88c-.42-.16-1.04-.35-2.19-.4-1.24-.06-1.61-.07-4.76-.07Zm0 2.76a5.3 5.3 0 1 1 0 10.6 5.3 5.3 0 0 1 0-10.6Zm0 1.62a3.68 3.68 0 1 0 0 7.36 3.68 3.68 0 0 0 0-7.36Zm5.48-2.9a1.24 1.24 0 1 1 0 2.48 1.24 1.24 0 0 1 0-2.48Z"/>'],
        'twitter'   => ['label' => __('site.social_twitter'), 'svg' => '<path d="M13.6823 10.6218 20.2391 3h-1.5535l-5.6919 6.6188L8.4456 3H3l6.8754 10.0074L3 21h1.5535l6.0117-6.9889L15.5544 21H21l-7.3177-10.3782Zm-2.1296 2.4742-.6967-.9965L5.1135 4.1697h2.3865l4.4748 6.4006.6967.9965 5.8144 8.3164h-2.3865l-4.7448-6.7872Z"/>'],
        'snapchat'  => ['label' => __('site.social_snapchat'), 'svg' => '<path d="M12.206 2.5c.31.003 2.273.054 3.42 1.39.736.857.96 1.99.99 3.04.012.42.005.83-.012 1.16l-.01.18c.13.07.32.13.55.13.31-.01.68-.1 1.08-.31a.69.69 0 0 1 .31-.08c.16 0 .32.04.45.11.2.1.33.28.34.5.01.28-.2.53-.65.7l-.27.1c-.42.16-.99.38-1.16.78-.09.21-.05.48.12.81l.01.01c.05.12 1.27 2.9 3.98 3.35.21.03.36.22.35.43 0 .06-.01.12-.04.18-.21.49-1.1.85-2.72 1.1-.05.07-.1.32-.14.49-.04.16-.08.32-.13.49-.06.2-.2.3-.42.3h-.06a2.7 2.7 0 0 1-.5-.07 5.7 5.7 0 0 0-1.16-.13c-.24 0-.49.02-.74.06-.48.08-.9.38-1.38.72-.69.5-1.47 1.06-2.66 1.06l-.16-.01h-.1c-1.19 0-1.96-.56-2.65-1.05-.49-.35-.9-.65-1.39-.73a4.6 4.6 0 0 0-.74-.06c-.45 0-.81.07-1.16.14-.2.04-.37.07-.51.07-.27 0-.4-.16-.46-.31-.05-.16-.09-.33-.13-.49-.04-.17-.09-.42-.14-.49-1.62-.25-2.51-.61-2.72-1.1a.42.42 0 0 1-.04-.18.42.42 0 0 1 .35-.43c2.71-.45 3.93-3.23 3.98-3.35l.01-.02c.17-.33.21-.6.12-.81-.17-.4-.74-.62-1.16-.78l-.27-.1c-.56-.22-.68-.5-.64-.73.05-.28.4-.47.7-.47.09 0 .17.02.24.05.43.22.82.31 1.14.31.27 0 .45-.07.55-.13l-.02-.18a13.6 13.6 0 0 1-.01-1.16c.03-1.05.25-2.18.99-3.04C9.71 2.56 11.67 2.5 12 2.5h.206Z"/>'],
        'tiktok'    => ['label' => __('site.social_tiktok'), 'svg' => '<path d="M16.6 5.82a4.28 4.28 0 0 1-1.06-2.82h-3.06v12.27a2.45 2.45 0 0 1-2.45 2.45 2.45 2.45 0 1 1 .64-4.81V9.78a5.55 5.55 0 0 0-.64-.04A5.52 5.52 0 1 0 15.55 15V8.72a7.3 7.3 0 0 0 4.27 1.37V7.03a4.28 4.28 0 0 1-3.22-1.21Z"/>'],
    ];
@endphp

{{-- Public footer — link columns + contact/social are admin-managed.
     Fed by LayoutComposer ($footerColumns, $footerSettings). --}}
<footer class="bg-charcoal text-gray-400 mt-16">
    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            {{-- Brand + contact + social --}}
            <div>
                <x-logo :size="44" :on-dark="true" class="mb-3" />
                @if(!empty($footerSettings['about']))
                    <p class="text-sm leading-relaxed mt-3">{{ $footerSettings['about'] }}</p>
                @endif

                {{-- Official contact --}}
                <ul class="mt-4 space-y-1 text-sm">
                    @if(!empty($footerSettings['phone']))
                        <li><a href="tel:{{ $footerSettings['phone'] }}" class="flex items-center gap-2 min-h-touch hover:text-white transition-colors" dir="ltr">
                            <x-icon name="phone" class="w-4 h-4 shrink-0" /> <span>{{ $footerSettings['phone'] }}</span>
                        </a></li>
                    @endif
                    @if(!empty($footerSettings['email']))
                        <li><a href="mailto:{{ $footerSettings['email'] }}" class="flex items-center gap-2 min-h-touch hover:text-white transition-colors" dir="ltr">
                            <x-icon name="envelope" class="w-4 h-4 shrink-0" /> <span>{{ $footerSettings['email'] }}</span>
                        </a></li>
                    @endif
                    @if(!empty($footerSettings['whatsapp']))
                        <li><a href="https://wa.me/{{ preg_replace('/\D/', '', $footerSettings['whatsapp']) }}" target="_blank" rel="noopener"
                               class="flex items-center gap-2 min-h-touch hover:text-white transition-colors" dir="ltr">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.32 4.97L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2"/></svg>
                            <span>{{ $footerSettings['whatsapp'] }}</span>
                        </a></li>
                    @endif
                </ul>

                {{-- Social icons --}}
                @php $hasSocial = collect(['instagram','twitter','snapchat','tiktok'])->contains(fn ($k) => !empty($footerSettings[$k])); @endphp
                @if($hasSocial)
                    <div class="flex flex-wrap gap-2 mt-4">
                        @foreach($socialBrands as $key => $brand)
                            @if(!empty($footerSettings[$key]))
                                <a href="{{ $footerSettings[$key] }}" target="_blank" rel="noopener"
                                   title="{{ $brand['label'] }}" aria-label="{{ $brand['label'] }}"
                                   class="w-10 h-10 rounded-full flex items-center justify-center bg-white/10 text-gray-200 hover:bg-white/20 hover:text-white transition-colors">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">{!! $brand['svg'] !!}</svg>
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Admin-managed link columns --}}
            @foreach($footerColumns as $col => $links)
                <div>
                    <h4 class="text-white font-semibold mb-3">{{ $colHeadings[$col] ?? $colHeadings[1] }}</h4>
                    <ul class="text-sm">
                        @foreach($links as $link)
                            <li><a href="{{ $link->resolved_url }}"
                                   @if($link->open_new_tab) target="_blank" rel="noopener" @endif
                                   class="flex items-center min-h-touch hover:text-white transition-colors">{{ $link->label }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        {{-- For-clinics CTA + copyright --}}
        <div class="border-t border-gray-800 mt-8 pt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-sm text-center sm:text-start">{!! __('site.footer_rights', ['year' => date('Y')]) !!}</p>
            <a href="{{ route('clinic.register') }}"
               class="inline-flex items-center justify-center min-h-touch bg-sage-600 text-white px-4 rounded-lg text-sm hover:bg-sage-700 transition-colors">
                @lang('site.nav_register_clinic')
            </a>
        </div>
    </div>
</footer>
