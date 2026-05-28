{{-- Clinic list (featured / top-rated / best-priced). $data['clinics'] + $section->config['source']. --}}
@php
    $clinics = $data['clinics'] ?? collect();
    $source  = data_get($section->config, 'source', 'featured');
@endphp
@if($clinics->isNotEmpty())
@php
    $defaultTitle  = __('site.' . ($source === 'top_rated' ? 'top_rated_clinics' : ($source === 'best_priced' ? 'best_priced_clinics' : 'featured_clinics')));
    $hasSubtitle   = in_array($source, ['top_rated', 'best_priced'], true);
    $subtitleKey   = $source === 'top_rated' ? 'top_rated_subtitle' : 'best_priced_subtitle';
    $viewAllParams = match ($source) {
        'top_rated'   => ['sort' => 'top_rated'],
        'best_priced' => ['sort' => 'cheapest'],
        default       => ['featured' => 1],
    };
    $gridCols = $source === 'featured' ? 'lg:grid-cols-4' : 'lg:grid-cols-3';
    $bgClass  = $source === 'top_rated' ? '' : 'bg-white';
@endphp
<section class="py-16 px-4 {{ $bgClass }}">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-end justify-between {{ $hasSubtitle ? 'mb-2' : 'mb-8' }}">
            <div>
                @if($source === 'featured')
                    <p class="reveal text-sm font-semibold tracking-widest text-gold-deep uppercase mb-2">@lang('site.featured_eyebrow')</p>
                @endif
                <h2 class="reveal font-display text-3xl font-bold text-charcoal" style="--reveal-delay:80ms">
                    {{ $section->title_ar && app()->getLocale() === 'ar' ? $section->title_ar : ($section->title_en && app()->getLocale() === 'en' ? $section->title_en : $defaultTitle) }}
                </h2>
            </div>
            <a href="{{ route('search', $viewAllParams) }}" class="reveal text-sage-600 text-sm font-semibold hover:text-sage-700 inline-flex items-center gap-1 whitespace-nowrap">
                @lang('site.view_all') <span class="rtl:rotate-180">→</span>
            </a>
        </div>
        @if($hasSubtitle)
            <p class="reveal text-gray-500 text-sm mb-8">@lang('site.' . $subtitleKey)</p>
        @endif
        <div class="grid grid-cols-1 sm:grid-cols-2 {{ $gridCols }} gap-6">
            @foreach($clinics as $i => $clinic)
                <div class="reveal" style="--reveal-delay:{{ ($i % 4) * 90 }}ms">
                    @include('public.partials.clinic-card', ['clinic' => $clinic, 'badgeContext' => $clinics])
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
