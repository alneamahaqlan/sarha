{{-- Stats band — 4 counters. $data['stats'] = ['clinics' => int, ...]. --}}
<section class="px-4">
    <div class="max-w-6xl mx-auto -mt-14 relative z-10">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">
            @foreach([
                ['icon' => 'building', 'value' => $data['stats']['clinics'] ?? 0, 'label' => 'stats_clinics'],
                ['icon' => 'map-pin', 'value' => $data['stats']['cities'] ?? 0, 'label' => 'stats_cities'],
                ['icon' => 'scale', 'value' => $data['stats']['specialties'] ?? 0, 'label' => 'stats_specialties'],
                ['icon' => 'clipboard', 'value' => $data['stats']['services'] ?? 0, 'label' => 'stats_services'],
            ] as $i => $stat)
                <div class="reveal reveal-zoom group relative bg-white/85 backdrop-blur rounded-3xl shadow-soft hover:shadow-soft-lg hover:-translate-y-1 transition-all duration-300 p-5 md:p-6 text-center overflow-hidden" style="--reveal-delay:{{ $i * 90 }}ms">
                    {{-- Soft gradient top accent — KPI-widget feel (Stripe/Linear). --}}
                    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-l from-sage-primary via-sage-medium to-gold-primary opacity-70"></div>
                    <div class="mx-auto mb-3 w-12 h-12 rounded-2xl bg-gradient-to-br from-sage-mist to-gold-whisper text-sage-primary flex items-center justify-center group-hover:scale-105 transition-transform">
                        <x-icon :name="$stat['icon']" class="w-5 h-5" />
                    </div>
                    <div class="font-display text-3xl md:text-4xl font-bold text-sage-deep tracking-tight" data-count="{{ $stat['value'] }}">0</div>
                    <div class="text-sm text-slate-warm mt-1">@lang('site.' . $stat['label'])</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
