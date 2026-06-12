@extends('layouts.public')

@section('title', __('site.compare_title_offer'))

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">@lang('site.compare_title_offer')</h1>
        <p class="text-sm text-gray-500 mt-1">@lang('site.compare_subtitle_offer')</p>
    </div>

    @if($offers->isEmpty())
        <div class="bg-white rounded-xl shadow-sm p-12 text-center">
            <x-icon name="scale" class="w-16 h-16 mx-auto mb-4 text-gray-300" />
            <p class="text-gray-600 mb-4">@lang('site.compare_empty_offer')</p>
            <a href="{{ route('search') }}" class="bg-sage-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-sage-700 transition-colors inline-block">
                @lang('site.account_start_browsing')
            </a>
        </div>
    @else
        @if($offers->count() < 2)
            <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-3 text-sm mb-4">
                @lang('site.compare_need_more_offer')
            </div>
        @endif

        <div class="overflow-x-auto bg-white rounded-xl shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="p-4 text-start text-gray-400 font-medium w-32">@lang('site.compare_attribute')</th>
                        @foreach($offers as $offer)
                            <th class="p-4 text-center align-top min-w-48">
                                <a href="{{ route('offer.show', ['slug' => $offer->clinic->slug, 'offer' => $offer->id]) }}" class="block group">
                                    <span class="font-bold text-gray-800 group-hover:text-sage-600 line-clamp-2">{{ $offer->title }}</span>
                                </a>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    {{-- Clinic --}}
                    <tr>
                        <td class="p-4 text-gray-500">@lang('site.compare_clinic')</td>
                        @foreach($offers as $offer)
                            <td class="p-4 text-center">
                                <a href="{{ route('clinic.show', $offer->clinic->slug) }}" class="text-sage-700 hover:text-sage-800 font-medium">{{ $offer->clinic->name }}</a>
                            </td>
                        @endforeach
                    </tr>
                    {{-- Linked service (service-type offers) --}}
                    <tr class="bg-gray-50/50">
                        <td class="p-4 text-gray-500">@lang('site.compare_service')</td>
                        @foreach($offers as $offer)
                            <td class="p-4 text-center text-gray-700">{{ $offer->service?->name ?: __('site.offer_type_general') }}</td>
                        @endforeach
                    </tr>
                    {{-- Price now --}}
                    <tr>
                        <td class="p-4 text-gray-500">@lang('site.compare_price_now')</td>
                        @foreach($offers as $offer)
                            <td class="p-4 text-center">
                                @if($offer->price !== null)
                                    <span class="font-bold text-sage-700 text-base">{{ number_format((float) $offer->price) }} <x-riyal /></span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    {{-- Old price --}}
                    <tr class="bg-gray-50/50">
                        <td class="p-4 text-gray-500">@lang('site.compare_old_price')</td>
                        @foreach($offers as $offer)
                            <td class="p-4 text-center">
                                @if($offer->old_price !== null)
                                    <span class="text-gray-400 line-through">{{ number_format((float) $offer->old_price) }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    {{-- Discount % --}}
                    <tr>
                        <td class="p-4 text-gray-500">@lang('site.compare_discount')</td>
                        @foreach($offers as $offer)
                            <td class="p-4 text-center">
                                @if($offer->discountPercentage())
                                    <span class="inline-flex items-center bg-red-50 text-red-600 font-bold text-xs px-2.5 py-1 rounded-full">-{{ $offer->discountPercentage() }}%</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    {{-- Saving amount --}}
                    <tr class="bg-gray-50/50">
                        <td class="p-4 text-gray-500">@lang('site.compare_saving')</td>
                        @foreach($offers as $offer)
                            <td class="p-4 text-center">
                                @if($offer->old_price !== null && $offer->price !== null && $offer->old_price > $offer->price)
                                    <span class="inline-flex items-center gap-1 bg-gold-whisper text-gold-deep font-semibold text-xs px-2.5 py-1 rounded-full">{{ number_format((float) $offer->old_price - (float) $offer->price) }} <x-riyal /></span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    {{-- Ends at --}}
                    <tr>
                        <td class="p-4 text-gray-500">@lang('site.compare_ends')</td>
                        @foreach($offers as $offer)
                            <td class="p-4 text-center text-gray-700">{{ $offer->ends_at?->translatedFormat('d M Y') ?: '—' }}</td>
                        @endforeach
                    </tr>
                    {{-- Actions --}}
                    <tr>
                        <td class="p-4"></td>
                        @foreach($offers as $offer)
                            <td class="p-4 text-center">
                                <a href="{{ route('offer.show', ['slug' => $offer->clinic->slug, 'offer' => $offer->id]) }}"
                                   class="inline-flex items-center justify-center gap-1.5 min-h-touch bg-sage-600 text-white px-4 rounded-lg text-sm font-semibold hover:bg-sage-700 transition-colors">
                                    <x-icon name="eye" class="w-4 h-4" /> @lang('site.home_view_offer')
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
