@extends('layouts.public')

@section('title', __('site.compare_title_service'))

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">@lang('site.compare_title_service')</h1>
        <p class="text-sm text-gray-500 mt-1">@lang('site.compare_subtitle_service')</p>
    </div>

    @if($services->isEmpty())
        <div class="bg-white rounded-xl shadow-sm p-12 text-center">
            <x-icon name="scale" class="w-16 h-16 mx-auto mb-4 text-gray-300" />
            <p class="text-gray-600 mb-4">@lang('site.compare_empty_service')</p>
            <a href="{{ route('search') }}" class="bg-sage-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-sage-700 transition-colors inline-block">
                @lang('site.account_start_browsing')
            </a>
        </div>
    @else
        @if($services->count() < 2)
            <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-3 text-sm mb-4">
                @lang('site.compare_need_more_service')
            </div>
        @endif

        <div class="overflow-x-auto bg-white rounded-xl shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="p-4 text-start text-gray-400 font-medium w-32">@lang('site.compare_attribute')</th>
                        @foreach($services as $service)
                            <th class="p-4 text-center align-top min-w-48">
                                <a href="{{ route('service.show', ['slug' => $service->clinic->slug, 'service' => $service->id]) }}" class="block group">
                                    <span class="font-bold text-gray-800 group-hover:text-sage-600 line-clamp-2">{{ $service->name }}</span>
                                </a>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    {{-- Clinic --}}
                    <tr>
                        <td class="p-4 text-gray-500">@lang('site.compare_clinic')</td>
                        @foreach($services as $service)
                            <td class="p-4 text-center">
                                <a href="{{ route('clinic.show', $service->clinic->slug) }}" class="text-sage-700 hover:text-sage-800 font-medium">{{ $service->clinic->name }}</a>
                            </td>
                        @endforeach
                    </tr>
                    {{-- Price — the headline number visitors came to compare. --}}
                    <tr class="bg-gray-50/50">
                        <td class="p-4 text-gray-500">@lang('site.compare_price')</td>
                        @foreach($services as $service)
                            <td class="p-4 text-center">
                                @if($service->price)
                                    <span class="font-bold text-sage-700 text-base">
                                        @if($service->price_from)<span class="text-[10px] font-normal text-gray-500">@lang('site.price_from')</span>@endif
                                        {{ number_format($service->price) }} <x-riyal />
                                    </span>
                                @else
                                    <span class="text-gray-400">@lang('site.call_for_inquiry')</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    {{-- Clinic rating --}}
                    <tr>
                        <td class="p-4 text-gray-500">@lang('site.compare_rating')</td>
                        @foreach($services as $service)
                            <td class="p-4 text-center">
                                @if(($service->clinic->google_reviews_avg_rating ?? 0) > 0)
                                    <span class="inline-flex items-center gap-1 font-semibold text-gray-800"><x-icon name="star-solid" class="w-4 h-4 text-yellow-500" /> {{ number_format($service->clinic->google_reviews_avg_rating, 1) }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    {{-- Specialties --}}
                    <tr class="bg-gray-50/50">
                        <td class="p-4 text-gray-500">@lang('site.compare_categories')</td>
                        @foreach($services as $service)
                            <td class="p-4 text-center">
                                <div class="flex flex-wrap justify-center gap-1">
                                    @forelse($service->categories->take(3) as $cat)
                                        <span class="inline-flex items-center gap-1 bg-sage-50 text-sage-600 text-xs px-2 py-0.5 rounded-full"><x-category-icon :emoji="$cat->emoji" :icon="$cat->icon" class="w-3.5 h-3.5" /> {{ $cat->display_name }}</span>
                                    @empty
                                        <span class="text-gray-400">—</span>
                                    @endforelse
                                </div>
                            </td>
                        @endforeach
                    </tr>
                    {{-- Description --}}
                    <tr>
                        <td class="p-4 text-gray-500">@lang('site.compare_description')</td>
                        @foreach($services as $service)
                            <td class="p-4 text-center text-gray-600 text-xs align-top">{{ $service->description ?: '—' }}</td>
                        @endforeach
                    </tr>
                    {{-- Actions --}}
                    <tr>
                        <td class="p-4"></td>
                        @foreach($services as $service)
                            <td class="p-4 text-center">
                                <a href="{{ route('clinic.book.form', ['slug' => $service->clinic->slug, 'service' => $service->id]) }}"
                                   class="inline-flex items-center justify-center gap-1.5 min-h-touch bg-sage-600 text-white px-4 rounded-lg text-sm font-semibold hover:bg-sage-700 transition-colors">
                                    <x-icon name="calendar" class="w-4 h-4" /> @lang('site.book_appointment')
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
