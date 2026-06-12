{{-- Homepage FAQ section — admin-authored Q&A accordion.
     $data['faqs'] is a cleaned array of { question, answer } (≤ FAQ_LIMIT).
     Title honors the admin's per-section override, falling back to the
     shared site.faqs_title lang key. --}}
@php
    $faqItems = collect($data['faqs'] ?? []);
    $faqTitle = $section->title_ar && app()->getLocale() === 'ar'
        ? $section->title_ar
        : ($section->title_en && app()->getLocale() === 'en' ? $section->title_en : __('site.faqs_title'));
@endphp

@if($faqItems->isNotEmpty())
    {{-- FAQPage structured data — earns rich-result eligibility in search. --}}
    @push('head')
    <script type="application/ld+json">{!! json_encode([
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $faqItems->map(fn ($f) => [
            '@type'          => 'Question',
            'name'           => $f['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']],
        ])->all(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endpush

    <section class="py-16 px-4" x-data="{ open: 0 }">
        <div class="max-w-3xl mx-auto">
            <div class="text-center mb-10">
                <h2 class="reveal font-display text-3xl font-bold text-charcoal">{{ $faqTitle }}</h2>
                <p class="reveal text-gray-500 mt-2" style="--reveal-delay:80ms">@lang('site.faqs_subtitle')</p>
            </div>

            <div class="reveal bg-white rounded-2xl ring-1 ring-gray-100 shadow-sm divide-y divide-gray-100 overflow-hidden">
                @foreach($faqItems as $i => $faq)
                    <div>
                        <button type="button"
                                @click="open === {{ $i }} ? open = null : open = {{ $i }}"
                                :aria-expanded="open === {{ $i }} ? 'true' : 'false'"
                                class="w-full flex items-center justify-between gap-4 text-start min-h-touch px-5 py-4 hover:bg-sage-mist/40 transition-colors">
                            <span class="font-semibold text-charcoal leading-relaxed">{{ $faq['question'] }}</span>
                            <span class="shrink-0 text-sage-primary transition-transform duration-200"
                                  :class="open === {{ $i }} ? 'rotate-180' : ''">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </span>
                        </button>
                        <div x-show="open === {{ $i }}" x-cloak
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0">
                            <p class="px-5 pb-4 text-gray-600 leading-relaxed whitespace-pre-line">{{ $faq['answer'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
