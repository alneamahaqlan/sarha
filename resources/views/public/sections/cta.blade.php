{{-- "Register your clinic" CTA at the bottom of the page. --}}
<section class="px-4 pb-20">
    <div class="max-w-6xl mx-auto">
        <div class="reveal reveal-zoom relative overflow-hidden rounded-3xl bg-gradient-to-br from-sage-700 to-sage-900 animate-gradient text-white text-center px-6 py-16 shine-host">
            <div class="absolute -top-16 -end-10 w-72 h-72 rounded-full bg-gold-primary/15 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-20 -start-10 w-72 h-72 rounded-full bg-sage-400/20 blur-3xl pointer-events-none"></div>
            <div class="relative max-w-2xl mx-auto">
                <h2 class="font-display text-3xl md:text-4xl font-bold mb-4">
                    {{ $section->title_ar && app()->getLocale() === 'ar' ? $section->title_ar : ($section->title_en && app()->getLocale() === 'en' ? $section->title_en : __('site.cta_title')) }}
                </h2>
                <p class="text-sage-100 text-lg mb-8">@lang('site.cta_subtitle')</p>
                <a href="{{ route('clinic.register') }}" class="inline-flex items-center gap-2 bg-white text-sage-700 px-8 py-3.5 rounded-xl font-bold hover:bg-sage-50 active:scale-[0.98] transition-all shadow-xl">
                    <x-icon name="building" class="w-5 h-5" /> @lang('site.cta_button')
                </a>
            </div>
        </div>
    </div>
</section>
