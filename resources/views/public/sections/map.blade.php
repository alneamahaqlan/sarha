{{-- Leaflet map — $data['mapClinics'] is the {id,name,slug,lat,lng} list. --}}
@php $mapClinics = $data['mapClinics'] ?? collect(); @endphp
@if($mapClinics->isNotEmpty())
<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-8">
            <h2 class="reveal font-display text-3xl font-bold text-charcoal">
                {{ $section->title_ar && app()->getLocale() === 'ar' ? $section->title_ar : ($section->title_en && app()->getLocale() === 'en' ? $section->title_en : __('site.map_title')) }}
            </h2>
            <p class="reveal text-gray-500 text-sm mt-1" style="--reveal-delay:80ms">@lang('site.map_subtitle')</p>
        </div>
        {{-- No `reveal` wrapper here: a CSS transform on a Leaflet ancestor breaks tile rendering. --}}
        <div>
            @include('public.partials.map', ['mapClinics' => $mapClinics, 'mapId' => 'home-map'])
        </div>
    </div>
</section>
@endif
