@extends('layouts.public')

@section('title', __('site.compare_title'))

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">@lang('site.compare_title')</h1>
        <p class="text-sm text-gray-500 mt-1">@lang('site.compare_subtitle')</p>
    </div>

    @if($clinics->isEmpty())
        <div class="bg-white rounded-xl shadow-sm p-12 text-center">
            <div class="text-6xl mb-4">⚖️</div>
            <p class="text-gray-600 mb-4">@lang('site.compare_empty')</p>
            <a href="{{ route('search') }}" class="bg-teal-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-teal-700 transition-colors inline-block">
                @lang('site.account_start_browsing')
            </a>
        </div>
    @else
        @if($clinics->count() < 2)
            <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-3 text-sm mb-4">
                @lang('site.compare_need_more')
            </div>
        @endif

        <div class="overflow-x-auto bg-white rounded-xl shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="p-4 text-start text-gray-400 font-medium w-32">@lang('site.compare_attribute')</th>
                        @foreach($clinics as $clinic)
                            <th class="p-4 text-center align-top min-w-48">
                                <a href="{{ route('clinic.show', $clinic->slug) }}" class="block group">
                                    @if($clinic->logo)
                                        <img src="{{ Storage::url($clinic->logo) }}" alt="{{ $clinic->name }}" loading="lazy"
                                             class="mx-auto h-16 w-16 rounded-full object-cover mb-2">
                                    @else
                                        <div class="mx-auto h-16 w-16 rounded-full bg-teal-100 flex items-center justify-center text-2xl mb-2">🏥</div>
                                    @endif
                                    <span class="font-bold text-gray-800 group-hover:text-teal-600">{{ $clinic->name }}</span>
                                </a>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    {{-- City --}}
                    <tr>
                        <td class="p-4 text-gray-500">@lang('site.compare_city')</td>
                        @foreach($clinics as $clinic)
                            <td class="p-4 text-center text-gray-700">{{ $clinic->city->display_name ?? '—' }}</td>
                        @endforeach
                    </tr>
                    {{-- Rating --}}
                    <tr class="bg-gray-50/50">
                        <td class="p-4 text-gray-500">@lang('site.compare_rating')</td>
                        @foreach($clinics as $clinic)
                            <td class="p-4 text-center">
                                @if(($clinic->google_reviews_avg_rating ?? 0) > 0)
                                    <span class="font-semibold text-gray-800">⭐ {{ number_format($clinic->google_reviews_avg_rating, 1) }}</span>
                                    <span class="text-xs text-gray-400 block">{{ __('site.reviews_count_label', ['count' => $clinic->google_reviews_count]) }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    {{-- Starting price --}}
                    <tr>
                        <td class="p-4 text-gray-500">@lang('site.compare_price')</td>
                        @foreach($clinics as $clinic)
                            <td class="p-4 text-center">
                                @if(! is_null($clinic->min_price))
                                    <span class="font-semibold text-teal-700">{{ __('site.starting_from', ['amount' => number_format($clinic->min_price)]) }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    {{-- Active services --}}
                    <tr class="bg-gray-50/50">
                        <td class="p-4 text-gray-500">@lang('site.compare_services')</td>
                        @foreach($clinics as $clinic)
                            <td class="p-4 text-center text-gray-700">{{ __('site.services_count', ['count' => $clinic->active_services_count ?? 0]) }}</td>
                        @endforeach
                    </tr>
                    {{-- Categories --}}
                    <tr>
                        <td class="p-4 text-gray-500">@lang('site.compare_categories')</td>
                        @foreach($clinics as $clinic)
                            <td class="p-4 text-center">
                                <div class="flex flex-wrap justify-center gap-1">
                                    @forelse($clinic->categories->take(3) as $cat)
                                        <span class="bg-teal-50 text-teal-600 text-xs px-2 py-0.5 rounded-full">{{ $cat->emoji ?? '' }} {{ $cat->display_name }}</span>
                                    @empty
                                        <span class="text-gray-400">—</span>
                                    @endforelse
                                </div>
                            </td>
                        @endforeach
                    </tr>
                    {{-- Featured --}}
                    <tr class="bg-gray-50/50">
                        <td class="p-4 text-gray-500">@lang('site.compare_featured')</td>
                        @foreach($clinics as $clinic)
                            <td class="p-4 text-center">
                                @if($clinic->is_featured)
                                    <span class="text-yellow-600">⭐ @lang('site.featured')</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    {{-- Actions --}}
                    <tr>
                        <td class="p-4"></td>
                        @foreach($clinics as $clinic)
                            <td class="p-4 text-center">
                                <a href="{{ route('clinic.show', $clinic->slug) }}"
                                   class="inline-block bg-teal-600 text-white px-4 py-2 rounded-lg text-xs font-semibold hover:bg-teal-700 transition-colors">
                                    @lang('site.compare_visit')
                                </a>
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
