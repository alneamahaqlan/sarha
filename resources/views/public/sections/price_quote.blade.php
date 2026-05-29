{{--
    Custom price quote — moved out of the clinic sidebar into a homepage
    section. Lets a visitor describe what they need and broadcasts the
    request to all complexes in the chosen cities; replies arrive back in
    the visitor's account inbox.
    Uses gold/sage gradient to stand out from neighbouring offer strips.
--}}
<section class="py-12 px-4">
    <div class="max-w-6xl mx-auto">
        <div class="reveal reveal-zoom relative overflow-hidden rounded-3xl bg-gradient-to-br from-gold-primary via-gold-deep to-sage-700 text-white px-6 py-12 md:py-16 shine-host">
            <div class="absolute -top-16 -end-10 w-72 h-72 rounded-full bg-white/10 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-20 -start-10 w-72 h-72 rounded-full bg-white/10 blur-3xl pointer-events-none"></div>

            <div class="relative grid md:grid-cols-[1fr_auto] items-center gap-6 max-w-4xl mx-auto">
                <div class="text-center md:text-start">
                    <h2 class="font-display text-2xl md:text-3xl font-bold mb-3">
                        {{ $section->title_ar && app()->getLocale() === 'ar' ? $section->title_ar : ($section->title_en && app()->getLocale() === 'en' ? $section->title_en : __('site.request_price_quote')) }}
                    </h2>
                    <p class="text-white/90 text-base md:text-lg">@lang('site.quote_request_subtitle')</p>
                </div>
                <div class="flex justify-center md:justify-end">
                    <a href="{{ route('quotes.request') }}"
                       class="inline-flex items-center gap-2 bg-white text-gold-deep px-7 py-3.5 rounded-xl font-bold hover:bg-gold-whisper active:scale-[0.98] transition-all shadow-xl whitespace-nowrap">
                        <x-icon name="envelope" class="w-5 h-5" />
                        @lang('site.request_price_quote')
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
