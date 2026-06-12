{{--
    FAQ (الأسئلة الشائعة) — standalone block rendered near the bottom of the
    complex page, just before "similar complexes". Managed through the Page
    Builder like every other section: toggle + title override live in
    "تخصيص صفحتي"; the Q&A rows are stored on the section's `content` bag.

    Expects:
      $faqsSection  ClinicPageSection (the active `faqs` row)
      $builder      ClinicPageBuilderService (title resolver)
--}}
@php
    $faqItems = collect($faqsSection->content ?? [])
        ->map(fn ($row) => [
            'question' => trim((string) ($row['question'] ?? '')),
            'answer'   => trim((string) ($row['answer'] ?? '')),
        ])
        ->filter(fn ($row) => $row['question'] !== '' && $row['answer'] !== '')
        ->take(\App\Models\ClinicPageSection::FAQ_LIMIT)
        ->values();
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
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $f['answer'],
            ],
        ])->all(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endpush

    <section class="mt-12" x-data="{ open: 0 }">
        <div class="mb-4">
            <h2 class="text-lg font-bold text-gray-800">{{ $builder->titleFor($faqsSection) }}</h2>
            <p class="text-sm text-gray-500 mt-0.5">@lang('site.faqs_subtitle')</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm divide-y divide-gray-100 overflow-hidden">
            @foreach($faqItems as $i => $faq)
                <div>
                    <button type="button"
                            @click="open === {{ $i }} ? open = null : open = {{ $i }}"
                            :aria-expanded="open === {{ $i }} ? 'true' : 'false'"
                            class="w-full flex items-center justify-between gap-4 text-start min-h-touch px-5 py-4 hover:bg-sage-50/60 transition-colors">
                        <span class="font-semibold text-gray-800 leading-relaxed">{{ $faq['question'] }}</span>
                        <span class="shrink-0 text-sage-600 transition-transform duration-200"
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
    </section>
@endif
