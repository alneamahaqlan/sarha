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
                <div class="reveal reveal-zoom bg-white rounded-2xl shadow-lg shadow-sage-900/5 ring-1 ring-gray-100 p-5 text-center" style="--reveal-delay:{{ $i * 90 }}ms">
                    <div class="mx-auto mb-3 w-11 h-11 rounded-xl bg-sage-mist text-sage-primary flex items-center justify-center">
                        <x-icon :name="$stat['icon']" class="w-5 h-5" />
                    </div>
                    <div class="font-display text-3xl md:text-4xl font-bold text-sage-deep" data-count="{{ $stat['value'] }}">0</div>
                    <div class="text-sm text-gray-500 mt-1">@lang('site.' . $stat['label'])</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
