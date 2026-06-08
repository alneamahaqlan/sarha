{{-- How it works — 3 step cards. --}}
<section class="py-20 md:py-28 px-4">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-12">
            <h2 class="reveal font-display text-3xl font-bold text-charcoal">
                {{ $section->title_ar && app()->getLocale() === 'ar' ? $section->title_ar : ($section->title_en && app()->getLocale() === 'en' ? $section->title_en : __('site.how_it_works_title')) }}
            </h2>
            <p class="reveal text-gray-500 mt-2" style="--reveal-delay:80ms">@lang('site.how_it_works_subtitle')</p>
        </div>
        <div class="relative grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="hidden md:block absolute top-10 inset-x-16 h-px bg-gradient-to-l from-transparent via-sage-soft to-transparent"></div>
            @foreach([['search', 'how_step_1'], ['scale', 'how_step_2'], ['phone', 'how_step_3']] as $i => [$icon, $key])
                <div class="reveal relative bg-white rounded-3xl shadow-soft hover:shadow-soft-lg hover:-translate-y-1 transition-all duration-300 p-8 text-center" style="--reveal-delay:{{ $i * 120 }}ms">
                    <div class="relative mx-auto mb-4 w-16 h-16 rounded-2xl bg-gradient-to-br from-sage-mist to-gold-whisper text-sage-primary flex items-center justify-center">
                        <x-icon :name="$icon" class="w-8 h-8" />
                        <span class="absolute -top-2 -end-2 w-7 h-7 rounded-full bg-gradient-to-br from-gold-primary to-gold-deep text-white flex items-center justify-center text-sm font-bold shadow-soft">{{ $i + 1 }}</span>
                    </div>
                    <h3 class="font-display font-bold text-charcoal mb-1.5">@lang('site.' . $key . '_title')</h3>
                    <p class="text-sm text-slate-warm leading-relaxed">@lang('site.' . $key . '_desc')</p>
                </div>
            @endforeach
        </div>
        <div class="reveal mt-6 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-3 text-sm flex items-center justify-center gap-2">
            <x-icon name="warning" class="w-4 h-4 shrink-0" /> @lang('site.how_not_appointment_notice')
        </div>
    </div>
</section>
