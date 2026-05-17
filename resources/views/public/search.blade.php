@extends('layouts.public')

@section('title', __('site.nav_search'))

@section('content')

<div class="max-w-7xl mx-auto px-4 py-8">
    {{-- Search Filters --}}
    <form action="{{ route('search') }}" method="GET" class="bg-white rounded-xl shadow-sm p-5 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <input
                type="text"
                name="q"
                value="{{ request('q') }}"
                placeholder="@lang('site.search_clinic_or_specialty')"
                class="border border-gray-200 rounded-lg px-4 py-2.5 text-gray-800 focus:outline-none focus:ring-2 focus:ring-teal-400"
            >
            <select name="city" class="border border-gray-200 rounded-lg px-4 py-2.5 text-gray-700 focus:outline-none focus:ring-2 focus:ring-teal-400">
                <option value="">@lang('site.search_all_cities')</option>
                @foreach($cities as $city)
                    <option value="{{ $city->id }}" @selected(request('city') == $city->id)>{{ $city->display_name }}</option>
                @endforeach
            </select>
            <select name="category" class="border border-gray-200 rounded-lg px-4 py-2.5 text-gray-700 focus:outline-none focus:ring-2 focus:ring-teal-400">
                <option value="">@lang('site.search_all_categories')</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(request('category') == $cat->id)>
                        {{ $cat->emoji ?? '' }} {{ $cat->display_name }}
                    </option>
                @endforeach
            </select>
            <select name="sort" class="border border-gray-200 rounded-lg px-4 py-2.5 text-gray-700 focus:outline-none focus:ring-2 focus:ring-teal-400">
                <option value="featured" @selected(($sort ?? 'featured') === 'featured')>@lang('site.sort_featured')</option>
                <option value="top_rated" @selected(($sort ?? '') === 'top_rated')>@lang('site.sort_top_rated')</option>
                <option value="cheapest" @selected(($sort ?? '') === 'cheapest')>@lang('site.sort_cheapest')</option>
                <option value="most_booked" @selected(($sort ?? '') === 'most_booked')>@lang('site.sort_most_booked')</option>
            </select>
            <button type="submit" class="bg-teal-600 text-white rounded-lg py-2.5 font-semibold hover:bg-teal-700 transition-colors">
                @lang('site.search_button')
            </button>
        </div>
    </form>

    {{-- Results --}}
    <div class="flex items-center justify-between mb-4">
        <p class="text-gray-600 text-sm">
            @if(request('q'))
                {{ __('site.search_results_for', ['count' => $clinics->total(), 'query' => request('q')]) }}
            @else
                {{ __('site.search_results', ['count' => $clinics->total()]) }}
            @endif
        </p>
        @if(request()->hasAny(['q', 'city', 'category']))
            <a href="{{ route('search') }}" class="text-sm text-teal-600 hover:underline">@lang('site.clear_filters')</a>
        @endif
    </div>

    @if($clinics->isEmpty())
        <div class="text-center py-16 text-gray-500">
            <div class="text-6xl mb-4">🔍</div>
            <p class="text-xl font-semibold mb-2">@lang('site.no_results_title')</p>
            <p>@lang('site.no_results_subtitle')</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach($clinics as $clinic)
                @include('public.partials.clinic-card', ['clinic' => $clinic])
            @endforeach
        </div>

        <div class="mt-8">
            {{ $clinics->links() }}
        </div>
    @endif
</div>

@endsection
