@php
    /**
     * Per-landing-page footer chrome. $lp = ['mode' => ..., 'config' => [...]].
     *   default  → the platform's global footer (unchanged).
     *   none     → no footer.
     *   minimal  → logo + copyright line only.
     *   custom   → own logo, about, link list, social, contact + colors.
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
    $copyright = filled($cfg['copyright'] ?? null) ? $cfg['copyright'] : __('site.footer_rights', ['year' => date('Y')]);

    $socialBrands = [
        'instagram' => '<path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16Zm0 4.38a5.3 5.3 0 1 0 0 10.6 5.3 5.3 0 0 0 0-10.6Zm0 1.62a3.68 3.68 0 1 1 0 7.36 3.68 3.68 0 0 1 0-7.36Zm5.48-2.9a1.24 1.24 0 1 1 0 2.48 1.24 1.24 0 0 1 0-2.48Z"/>',
        'twitter'   => '<path d="M13.6823 10.6218 20.2391 3h-1.5535l-5.6919 6.6188L8.4456 3H3l6.8754 10.0074L3 21h1.5535l6.0117-6.9889L15.5544 21H21l-7.3177-10.3782Zm-2.1296 2.4742-.6967-.9965L5.1135 4.1697h2.3865l4.4748 6.4006.6967.9965 5.8144 8.3164h-2.3865l-4.7448-6.7872Z"/>',
        'snapchat'  => '<path d="M12.206 2.5c.31.003 2.273.054 3.42 1.39.736.857.96 1.99.99 3.04.012.42.005.83-.012 1.16l-.01.18c.13.07.32.13.55.13.31-.01.68-.1 1.08-.31a.69.69 0 0 1 .31-.08c.16 0 .32.04.45.11.2.1.33.28.34.5.01.28-.2.53-.65.7l-.27.1c-.42.16-.99.38-1.16.78-.09.21-.05.48.12.81l.01.01c.05.12 1.27 2.9 3.98 3.35.21.03.36.22.35.43 0 .06-.01.12-.04.18-.21.49-1.1.85-2.72 1.1-.05.07-.1.32-.14.49-.04.16-.08.32-.13.49-.06.2-.2.3-.42.3h-.06a2.7 2.7 0 0 1-.5-.07 5.7 5.7 0 0 0-1.16-.13c-.24 0-.49.02-.74.06-.48.08-.9.38-1.38.72-.69.5-1.47 1.06-2.66 1.06l-.16-.01h-.1c-1.19 0-1.96-.56-2.65-1.05-.49-.35-.9-.65-1.39-.73a4.6 4.6 0 0 0-.74-.06c-.45 0-.81.07-1.16.14-.2.04-.37.07-.51.07-.27 0-.4-.16-.46-.31-.05-.16-.09-.33-.13-.49-.04-.17-.09-.42-.14-.49-1.62-.25-2.51-.61-2.72-1.1a.42.42 0 0 1-.04-.18.42.42 0 0 1 .35-.43c2.71-.45 3.93-3.23 3.98-3.35l.01-.02c.17-.33.21-.6.12-.81-.17-.4-.74-.62-1.16-.78l-.27-.1c-.56-.22-.68-.5-.64-.73.05-.28.4-.47.7-.47.09 0 .17.02.24.05.43.22.82.31 1.14.31.27 0 .45-.07.55-.13l-.02-.18a13.6 13.6 0 0 1-.01-1.16c.03-1.05.25-2.18.99-3.04C9.71 2.56 11.67 2.5 12 2.5h.206Z"/>',
        'tiktok'    => '<path d="M16.6 5.82a4.28 4.28 0 0 1-1.06-2.82h-3.06v12.27a2.45 2.45 0 0 1-2.45 2.45 2.45 2.45 0 1 1 .64-4.81V9.78a5.55 5.55 0 0 0-.64-.04A5.52 5.52 0 1 0 15.55 15V8.72a7.3 7.3 0 0 0 4.27 1.37V7.03a4.28 4.28 0 0 1-3.22-1.21Z"/>',
    ];
@endphp

@if($mode === 'default')
    @include('layouts.partials.footer')
@elseif($mode === 'none')
    {{-- footer intentionally hidden --}}
@elseif($mode === 'minimal')
    @php $bg = $hex($cfg['bg_color'] ?? null); $text = $hex($cfg['text_color'] ?? null); @endphp
    <footer class="mt-16 {{ $bg ? '' : 'bg-charcoal' }}"
            @if($bg) style="background-color: {{ $bg }}" @endif>
        <div class="max-w-7xl mx-auto px-4 py-8 flex flex-col items-center gap-3 text-center"
             style="color: {{ $text ?: '#9ca3af' }}">
            @if($logoImage)
                <img src="{{ $logoImage }}" alt="{{ $page->title ?? __('site.brand') }}" class="h-10 w-auto object-contain">
            @else
                <x-logo :size="40" :on-dark="true" />
            @endif
            <p class="text-sm">{!! $copyright !!}</p>
        </div>
    </footer>
@elseif($mode === 'custom')
    @php
        $bg     = $hex($cfg['bg_color'] ?? null);
        $text   = $hex($cfg['text_color'] ?? null);
        $links  = collect($cfg['links'] ?? [])->filter(fn ($l) => filled($l['label'] ?? null));
        $social = $cfg['social'] ?? [];
        $onDark = $bg === null; // default charcoal bg → light logo
        $footerStyle = trim(($bg ? "background-color: {$bg};" : '') . ' color: ' . ($text ?: '#9ca3af') . ';');
    @endphp
    <footer class="mt-16 {{ $bg ? '' : 'bg-charcoal' }}" style="{{ $footerStyle }}">
        <div class="max-w-7xl mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                {{-- Brand + about + contact + social --}}
                <div>
                    @if($logoImage)
                        <img src="{{ $logoImage }}" alt="{{ $page->title ?? __('site.brand') }}" class="h-11 w-auto object-contain mb-3">
                    @else
                        <x-logo :size="44" :on-dark="$onDark" class="mb-3" />
                    @endif
                    @if(filled($cfg['about'] ?? null))
                        <p class="text-sm leading-relaxed mt-3">{{ $cfg['about'] }}</p>
                    @endif

                    <ul class="mt-4 space-y-1 text-sm">
                        @if(filled($cfg['phone'] ?? null))
                            <li><a href="tel:{{ $cfg['phone'] }}" class="flex items-center gap-2 min-h-touch hover:opacity-70" dir="ltr">
                                <x-icon name="phone" class="w-4 h-4 shrink-0" /> <span>{{ $cfg['phone'] }}</span>
                            </a></li>
                        @endif
                        @if(filled($cfg['email'] ?? null))
                            <li><a href="mailto:{{ $cfg['email'] }}" class="flex items-center gap-2 min-h-touch hover:opacity-70" dir="ltr">
                                <x-icon name="envelope" class="w-4 h-4 shrink-0" /> <span>{{ $cfg['email'] }}</span>
                            </a></li>
                        @endif
                        @if(filled($cfg['whatsapp'] ?? null))
                            <li><a href="https://wa.me/{{ preg_replace('/\D/', '', $cfg['whatsapp']) }}" target="_blank" rel="noopener"
                                   class="flex items-center gap-2 min-h-touch hover:opacity-70" dir="ltr">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.32 4.97L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2"/></svg>
                                <span>{{ $cfg['whatsapp'] }}</span>
                            </a></li>
                        @endif
                    </ul>

                    @php $hasSocial = collect(['instagram','twitter','snapchat','tiktok'])->contains(fn ($k) => filled($social[$k] ?? null)); @endphp
                    @if($hasSocial)
                        <div class="flex flex-wrap gap-2 mt-4">
                            @foreach($socialBrands as $key => $svg)
                                @if(filled($social[$key] ?? null))
                                    <a href="{{ $safeUrl($social[$key]) }}" target="_blank" rel="noopener" aria-label="{{ $key }}"
                                       class="w-10 h-10 rounded-full flex items-center justify-center bg-white/10 hover:bg-white/20 transition-colors">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">{!! $svg !!}</svg>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Custom link list --}}
                @if($links->isNotEmpty())
                    <div>
                        <ul class="text-sm space-y-1">
                            @foreach($links as $link)
                                <li><a href="{{ $safeUrl($link['url'] ?? '#') }}"
                                       @if(!empty($link['new_tab'])) target="_blank" rel="noopener" @endif
                                       class="flex items-center min-h-touch hover:opacity-70">{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="border-t border-current/10 mt-8 pt-6 text-sm text-center sm:text-start">
                <p>{!! $copyright !!}</p>
            </div>
        </div>
    </footer>
@endif
