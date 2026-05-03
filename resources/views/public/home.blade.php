@extends('layouts.public')

@section('title', __('site.brand'))

@section('content')

{{-- Hero Section --}}
<section class="bg-gradient-to-br from-purple-700 to-purple-900 text-white py-20 px-4">
    <div class="max-w-4xl mx-auto text-center">
        <h1 class="text-4xl md:text-5xl font-bold mb-4">@lang('site.hero_title')</h1>
        <p class="text-purple-200 text-lg mb-10">@lang('site.hero_subtitle')</p>

        <form action="{{ route('search') }}" method="GET" class="bg-white rounded-2xl p-4 shadow-xl">
            <div class="flex flex-col md:flex-row gap-3">
                <input
                    type="text"
                    name="q"
                    placeholder="@lang('site.search_placeholder')"
                    class="flex-1 border border-gray-200 rounded-xl px-4 py-3 text-gray-800 focus:outline-none focus:ring-2 focus:ring-purple-500"
                >
                <select name="city" class="border border-gray-200 rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500 min-w-40">
                    <option value="">@lang('site.search_all_cities')</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->id }}">{{ $city->display_name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-purple-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-purple-700 transition-colors whitespace-nowrap">
                    @lang('site.search_button')
                </button>
            </div>
        </form>
    </div>
</section>

{{-- Categories --}}
<section class="py-12 px-4">
    <div class="max-w-7xl mx-auto">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">@lang('site.browse_categories')</h2>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-7 gap-4">
            @foreach($categories->take(14) as $category)
                <a href="{{ route('search', ['category' => $category->id]) }}"
                   class="flex flex-col items-center p-4 bg-white rounded-xl shadow-sm hover:shadow-md hover:ring-2 hover:ring-purple-200 transition-all text-center group">
                    <span class="text-3xl mb-2">{{ $category->emoji ?? '🏥' }}</span>
                    <span class="text-xs text-gray-700 group-hover:text-purple-600 font-medium">{{ $category->display_name }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- Featured Clinics --}}
@if($featuredClinics->isNotEmpty())
<section class="py-12 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">@lang('site.featured_clinics')</h2>
            <a href="{{ route('search', ['featured' => 1]) }}" class="text-purple-600 text-sm hover:underline">@lang('site.view_all')</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($featuredClinics as $clinic)
                @include('public.partials.clinic-card', ['clinic' => $clinic])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- CTA for clinics --}}
<section class="py-16 px-4 bg-purple-600 text-white text-center">
    <div class="max-w-2xl mx-auto">
        <h2 class="text-3xl font-bold mb-4">@lang('site.cta_title')</h2>
        <p class="text-purple-100 mb-8">@lang('site.cta_subtitle')</p>
        <a href="#" class="bg-white text-purple-600 px-8 py-3 rounded-xl font-bold hover:bg-purple-50 transition-colors">
            @lang('site.cta_button')
        </a>
    </div>
</section>

@endsection
