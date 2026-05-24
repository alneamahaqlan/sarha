@extends('layouts.public')

@section('title', __('site.brand'))

@section('content')

{{-- Hero Section --}}
<section class="bg-gradient-to-br from-teal-700 to-teal-900 text-white py-20 px-4">
    <div class="max-w-4xl mx-auto text-center">
        @if($user)
            <p class="text-teal-100 text-base md:text-lg mb-3">{{ __('site.greeting_welcome', ['name' => $user->name ?: '']) }}</p>
        @endif
        <h1 class="text-4xl md:text-5xl font-bold mb-4">@lang('site.hero_title')</h1>
        <p class="text-teal-200 text-lg mb-10">@lang('site.hero_subtitle')</p>

        <form action="{{ route('search') }}" method="GET" class="bg-white rounded-2xl p-4 shadow-xl">
            <div class="flex flex-col md:flex-row gap-3">
                <input
                    type="text"
                    name="q"
                    placeholder="@lang('site.search_placeholder')"
                    class="flex-1 border border-gray-200 rounded-xl px-4 py-3 text-gray-800 focus:outline-none focus:ring-2 focus:ring-teal-500"
                >
                <select name="city" class="border border-gray-200 rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-teal-500 min-w-40">
                    <option value="">@lang('site.search_all_cities')</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->id }}">{{ $city->display_name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-teal-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-teal-700 transition-colors whitespace-nowrap">
                    @lang('site.search_button')
                </button>
            </div>
        </form>

        {{-- Progressive enhancement: jump straight to "nearest" results via geolocation --}}
        <div class="mt-4">
            <button type="button" id="nearest-btn" onclick="saerhaFindNearest()"
                    class="inline-flex items-center gap-1.5 text-teal-100 hover:text-white text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span id="nearest-btn-label">@lang('site.use_my_location')</span>
            </button>
        </div>
    </div>
</section>

{{-- Categories --}}
<section class="py-12 px-4">
    <div class="max-w-7xl mx-auto">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">@lang('site.browse_categories')</h2>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-7 gap-4">
            @foreach($categories->take(14) as $category)
                <a href="{{ route('search', ['category' => $category->id]) }}"
                   class="flex flex-col items-center p-4 bg-white rounded-xl shadow-sm hover:shadow-md hover:ring-2 hover:ring-teal-200 transition-all text-center group">
                    <x-category-icon :emoji="$category->emoji" class="w-8 h-8 mb-2 text-teal-600" />
                    <span class="text-xs text-gray-700 group-hover:text-teal-600 font-medium">{{ $category->display_name }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- How it works --}}
<section class="py-14 px-4">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-10">
            <h2 class="text-2xl font-bold text-gray-800">@lang('site.how_it_works_title')</h2>
            <p class="text-gray-500 mt-2">@lang('site.how_it_works_subtitle')</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach([['search', 'how_step_1', 'teal'], ['scale', 'how_step_2', 'amber'], ['phone', 'how_step_3', 'emerald']] as $i => [$icon, $key, $color])
                <div class="bg-white rounded-xl border border-gray-100 p-6 text-center relative">
                    <div class="absolute top-3 start-3 w-7 h-7 rounded-full bg-{{ $color }}-100 text-{{ $color }}-700 flex items-center justify-center text-sm font-bold">
                        {{ $i + 1 }}
                    </div>
                    <div class="mb-3 flex justify-center text-{{ $color }}-600"><x-icon :name="$icon" class="w-10 h-10" /></div>
                    <h3 class="font-bold text-gray-800 mb-1">@lang('site.' . $key . '_title')</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">@lang('site.' . $key . '_desc')</p>
                </div>
            @endforeach
        </div>
        <div class="mt-6 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-3 text-sm flex items-center justify-center gap-2">
            <x-icon name="warning" class="w-4 h-4 shrink-0" /> @lang('site.how_not_appointment_notice')
        </div>
    </div>
</section>

{{-- Featured Clinics --}}
@if($featuredClinics->isNotEmpty())
<section class="py-12 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">@lang('site.featured_clinics')</h2>
            <a href="{{ route('search', ['featured' => 1]) }}" class="text-teal-600 text-sm hover:underline">@lang('site.view_all')</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($featuredClinics as $clinic)
                @include('public.partials.clinic-card', ['clinic' => $clinic, 'badgeContext' => $featuredClinics])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Top-rated clinics --}}
@if(($topRatedClinics ?? collect())->isNotEmpty())
<section class="py-12 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-2xl font-bold text-gray-800">@lang('site.top_rated_clinics')</h2>
            <a href="{{ route('search', ['sort' => 'top_rated']) }}" class="text-teal-600 text-sm hover:underline">@lang('site.view_all')</a>
        </div>
        <p class="text-gray-500 text-sm mb-6">@lang('site.top_rated_subtitle')</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($topRatedClinics as $clinic)
                @include('public.partials.clinic-card', ['clinic' => $clinic, 'badgeContext' => $topRatedClinics])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Best-priced clinics --}}
@if(($bestPricedClinics ?? collect())->isNotEmpty())
<section class="py-12 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-2xl font-bold text-gray-800">@lang('site.best_priced_clinics')</h2>
            <a href="{{ route('search', ['sort' => 'cheapest']) }}" class="text-teal-600 text-sm hover:underline">@lang('site.view_all')</a>
        </div>
        <p class="text-gray-500 text-sm mb-6">@lang('site.best_priced_subtitle')</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($bestPricedClinics as $clinic)
                @include('public.partials.clinic-card', ['clinic' => $clinic, 'badgeContext' => $bestPricedClinics])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Map of clinics --}}
@if(($mapClinics ?? collect())->isNotEmpty())
<section class="py-12 px-4 bg-white">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">@lang('site.map_title')</h2>
            <p class="text-gray-500 text-sm mt-1">@lang('site.map_subtitle')</p>
        </div>
        @include('public.partials.map', ['mapClinics' => $mapClinics, 'mapId' => 'home-map'])
    </div>
</section>
@endif

{{-- CTA for clinics --}}
<section class="py-16 px-4 bg-teal-600 text-white text-center">
    <div class="max-w-2xl mx-auto">
        <h2 class="text-3xl font-bold mb-4">@lang('site.cta_title')</h2>
        <p class="text-teal-100 mb-8">@lang('site.cta_subtitle')</p>
        <a href="{{ route('clinic.register') }}" class="bg-white text-teal-600 px-8 py-3 rounded-xl font-bold hover:bg-teal-50 transition-colors">
            @lang('site.cta_button')
        </a>
    </div>
</section>

@push('scripts')
<script>
    function saerhaFindNearest() {
        var btn = document.getElementById('nearest-btn');
        var label = document.getElementById('nearest-btn-label');
        if (!navigator.geolocation) { window.location.href = @json(route('search', ['sort' => 'nearest'])); return; }
        var original = label.textContent;
        label.textContent = @json(__('site.locating'));
        btn.disabled = true;
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                var url = new URL(@json(route('search')));
                url.searchParams.set('sort', 'nearest');
                url.searchParams.set('lat', pos.coords.latitude);
                url.searchParams.set('lng', pos.coords.longitude);
                window.location.href = url.toString();
            },
            function () {
                label.textContent = original;
                btn.disabled = false;
                alert(@json(__('site.location_denied')));
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }
</script>
@endpush

@endsection
