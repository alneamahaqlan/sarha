@extends('layouts.public')

@section('title', __('site.compare_title_package'))

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">@lang('site.compare_title_package')</h1>
        <p class="text-sm text-gray-500 mt-1">@lang('site.compare_subtitle_package')</p>
    </div>

    @if($packages->isEmpty())
        <div class="bg-white rounded-xl shadow-sm p-12 text-center">
            <x-icon name="scale" class="w-16 h-16 mx-auto mb-4 text-gray-300" />
            <p class="text-gray-600 mb-4">@lang('site.compare_empty_package')</p>
            <a href="{{ route('search') }}" class="bg-sage-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-sage-700 transition-colors inline-block">
                @lang('site.account_start_browsing')
            </a>
        </div>
    @else
        @if($packages->count() < 2)
            <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-3 text-sm mb-4">
                @lang('site.compare_need_more_package')
            </div>
        @endif

        <div class="overflow-x-auto bg-white rounded-xl shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="p-4 text-start text-gray-400 font-medium w-32">@lang('site.compare_attribute')</th>
                        @foreach($packages as $package)
                            <th class="p-4 text-center align-top min-w-48">
                                <a href="{{ route('package.show', ['slug' => $package->clinic->slug, 'package' => $package->id]) }}" class="block group">
                                    <span class="font-bold text-gray-800 group-hover:text-sage-600 line-clamp-2">{{ $package->name }}</span>
                                </a>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    {{-- Clinic --}}
                    <tr>
                        <td class="p-4 text-gray-500">@lang('site.compare_clinic')</td>
                        @foreach($packages as $package)
                            <td class="p-4 text-center">
                                <a href="{{ route('clinic.show', $package->clinic->slug) }}" class="text-sage-700 hover:text-sage-800 font-medium">{{ $package->clinic->name }}</a>
                            </td>
                        @endforeach
                    </tr>
                    {{-- Price --}}
                    <tr class="bg-gray-50/50">
                        <td class="p-4 text-gray-500">@lang('site.compare_price')</td>
                        @foreach($packages as $package)
                            <td class="p-4 text-center">
                                <span class="font-bold text-sage-700 text-base">{{ number_format((float) $package->price) }} <x-riyal /></span>
                            </td>
                        @endforeach
                    </tr>
                    {{-- Old price --}}
                    <tr>
                        <td class="p-4 text-gray-500">@lang('site.compare_old_price')</td>
                        @foreach($packages as $package)
                            <td class="p-4 text-center">
                                @if($package->old_price)
                                    <span class="text-gray-400 line-through">{{ number_format((float) $package->old_price) }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    {{-- Discount % --}}
                    <tr class="bg-gray-50/50">
                        <td class="p-4 text-gray-500">@lang('site.compare_discount')</td>
                        @foreach($packages as $package)
                            <td class="p-4 text-center">
                                @if($package->discountPercentage())
                                    <span class="inline-flex items-center bg-red-50 text-red-600 font-bold text-xs px-2.5 py-1 rounded-full">-{{ $package->discountPercentage() }}%</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    {{-- Included services — the heart of a package comparison. --}}
                    <tr>
                        <td class="p-4 text-gray-500 align-top">@lang('site.compare_included_services')</td>
                        @foreach($packages as $package)
                            <td class="p-4 text-start align-top">
                                @if($package->services->isNotEmpty())
                                    <ul class="space-y-1">
                                        @foreach($package->services as $svc)
                                            <li class="flex items-start gap-1.5 text-gray-700 text-xs">
                                                <x-icon name="check-circle" class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0 mt-0.5" />
                                                <span>{{ $svc->name }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="text-gray-400 block text-center">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    {{-- Services count --}}
                    <tr class="bg-gray-50/50">
                        <td class="p-4 text-gray-500">@lang('site.compare_services_count')</td>
                        @foreach($packages as $package)
                            <td class="p-4 text-center text-gray-700 font-semibold">{{ $package->services->count() }}</td>
                        @endforeach
                    </tr>
                    {{-- Expires at --}}
                    <tr>
                        <td class="p-4 text-gray-500">@lang('site.compare_ends')</td>
                        @foreach($packages as $package)
                            <td class="p-4 text-center text-gray-700">{{ $package->expires_at?->translatedFormat('d M Y') ?: '—' }}</td>
                        @endforeach
                    </tr>
                    {{-- Actions --}}
                    <tr>
                        <td class="p-4"></td>
                        @foreach($packages as $package)
                            <td class="p-4 text-center">
                                <a href="{{ route('package.show', ['slug' => $package->clinic->slug, 'package' => $package->id]) }}"
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
