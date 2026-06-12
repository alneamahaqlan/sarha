{{-- FAQ block — admin-managed Q&A (also powers FAQPage JSON-LD). --}}
@php
    $items = collect($cfg['items'] ?? [])->filter(fn ($i) => filled($i['q'] ?? null) && filled($i['a'] ?? null))->values();
@endphp
@if($items->isNotEmpty())
    <section class="max-w-3xl mx-auto px-4 py-12">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">{{ $cfg['heading'] ?? __('site.lp_faq_title') }}</h2>
        <div class="space-y-3" x-data="{ open: null }">
            @foreach($items as $i => $item)
                <div class="bg-white rounded-xl ring-1 ring-gray-100 overflow-hidden">
                    <button type="button" class="w-full flex items-center justify-between gap-3 p-4 text-start font-semibold text-gray-800"
                            @click="open === {{ $i }} ? open = null : open = {{ $i }}">
                        <span>{{ $item['q'] }}</span>
                        <svg class="w-5 h-5 flex-shrink-0 transition-transform" :class="open === {{ $i }} ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open === {{ $i }}" x-collapse x-cloak class="px-4 pb-4 text-gray-600 leading-relaxed">{{ $item['a'] }}</div>
                </div>
            @endforeach
        </div>
    </section>
@endif
