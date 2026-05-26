{{-- Share buttons --}}
@php $shareUrl = url()->current(); @endphp

<div class="flex items-center gap-2" x-data="{ copied: false }">
    <span class="text-sm text-gray-600">@lang('site.share')</span>

    {{-- WhatsApp share --}}
    <a href="https://wa.me/?text={{ urlencode($clinic->name . ' — ' . $shareUrl) }}"
       target="_blank" rel="noopener"
       class="w-9 h-9 rounded-full bg-green-50 text-green-600 hover:bg-green-100 flex items-center justify-center transition-colors"
       title="WhatsApp">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.32 4.97L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2"/>
        </svg>
    </a>

    {{-- Twitter share --}}
    <a href="https://twitter.com/intent/tweet?text={{ urlencode($clinic->name) }}&url={{ urlencode($shareUrl) }}"
       target="_blank" rel="noopener"
       class="w-9 h-9 rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200 flex items-center justify-center transition-colors"
       title="X (Twitter)">
        𝕏
    </a>

    {{-- Copy link --}}
    <button type="button"
            @click="navigator.clipboard.writeText('{{ $shareUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
            class="w-9 h-9 rounded-full bg-sage-50 text-sage-600 hover:bg-sage-100 flex items-center justify-center transition-colors"
            :title="copied ? '@lang('site.link_copied')' : '@lang('site.copy_link')'">
        <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
        </svg>
        <svg x-show="copied" x-cloak class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
    </button>
</div>
