@props([
    'variant' => 'classic',   // classic | ai
    'text' => true,           // show the wordmark next to the mark
    'onDark' => false,         // cream letter for dark backgrounds
    'size' => 40,              // mark size in px
])
@php
    $letter = $onDark ? '#FAF7F2' : '#2D6A5C';
    $isAr = app()->getLocale() === 'ar';
    $word = $isAr ? 'دليل' : 'Daleel';
    $sub  = $isAr ? 'المجمعات الطبية' : 'Medical Directory';
    $wordColor = $onDark ? 'text-cream' : 'text-sage-deep';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    @if($variant === 'ai')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 90 90" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M 20 24 Q 20 20, 24 20 L 56 20 Q 62 20, 62 26 L 62 44 Q 62 58, 48 62 L 26 62 Q 20 62, 20 56 L 20 24 Z M 30 30 L 30 52 L 46 52 Q 52 52, 52 44 L 52 32 Q 52 30, 50 30 L 30 30 Z" fill="{{ $letter }}"/>
            <line class="ai-line ai-line-1" x1="62" y1="22" x2="76" y2="14" stroke="#6B4985" stroke-width="1.5" stroke-linecap="round"/>
            <line class="ai-line ai-line-2" x1="62" y1="22" x2="80" y2="28" stroke="#6B4985" stroke-width="1.5" stroke-linecap="round"/>
            <line class="ai-line ai-line-3" x1="76" y1="14" x2="80" y2="28" stroke="#8B6FA8" stroke-width="1.2" stroke-linecap="round" opacity="0.7"/>
            <circle class="ai-dot ai-dot-1" cx="62" cy="22" r="4" fill="#6B4985"/>
            <circle class="ai-dot ai-dot-2" cx="76" cy="14" r="3" fill="#8B6FA8"/>
            <circle class="ai-dot ai-dot-3" cx="80" cy="28" r="3" fill="#8B6FA8"/>
        </svg>
    @else
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M 20 22 Q 20 18, 24 18 L 56 18 Q 62 18, 62 24 L 62 42 Q 62 56, 48 60 L 26 60 Q 20 60, 20 54 L 20 22 Z M 30 28 L 30 50 L 46 50 Q 52 50, 52 42 L 52 30 Q 52 28, 50 28 L 30 28 Z" fill="{{ $letter }}"/>
            <circle cx="62" cy="22" r="5" fill="#C9A961"/>
        </svg>
    @endif

    @if($text)
        <span class="leading-none">
            <span class="font-display font-bold text-xl {{ $wordColor }}">{{ $word }}</span>
            <span class="block text-[10px] tracking-[0.2em] text-gold-deep mt-1">{{ $sub }}</span>
        </span>
    @endif
</span>
