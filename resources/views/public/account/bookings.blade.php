@extends('layouts.public')

@section('title', __('site.account_my_bookings'))

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">

    {{-- Header — mirrors the profile page for a consistent account experience --}}
    <div class="bg-gradient-to-r from-sage-600 to-sage-800 text-white rounded-2xl p-6 mb-6">
        <h1 class="text-2xl font-bold mb-2">{{ $user->name ?: __('site.account_my_bookings') }}</h1>
        <p class="text-sage-100" dir="ltr">{{ $user->phone }}</p>
        <div class="flex flex-wrap gap-4 mt-4 text-sm">
            <span class="inline-flex items-center gap-1.5 bg-white/20 px-3 py-1 rounded-full">
                <x-icon name="calendar" class="w-4 h-4" /> {{ __('site.bookings_count', ['count' => $bookingsCount]) }}
            </span>
            <span class="inline-flex items-center gap-1.5 bg-white/20 px-3 py-1 rounded-full">
                <x-icon name="star" class="w-4 h-4" /> {{ __('site.favorites_count', ['count' => $favoritesCount]) }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-1">@include('public.account._nav')</div>

        <div class="lg:col-span-3 space-y-3">
            {{-- Filter tabs: self vs relatives vs all. Mirrors the
                 booking_for radio on the form so users can quickly find
                 a booking they placed for a parent or child. --}}
            <div class="flex flex-wrap items-center gap-1.5 bg-white rounded-xl p-2 shadow-sm">
                @php
                    $filterTabs = [
                        'all'       => __('site.account_bookings_filter_all'),
                        'self'      => __('site.account_bookings_filter_self'),
                        'relatives' => __('site.account_bookings_filter_relatives'),
                    ];
                @endphp
                @foreach($filterTabs as $key => $label)
                    <a href="{{ route('account.bookings', $key === 'all' ? [] : ['filter' => $key]) }}"
                       class="px-3 py-1.5 rounded-md text-sm transition-colors {{ ($filter ?? 'all') === $key ? 'bg-sage-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            @forelse($bookings as $booking)
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <div class="flex items-start justify-between mb-3 gap-4">
                        <div class="flex-1">
                            <a href="{{ route('clinic.show', $booking->clinic->slug) }}"
                               class="font-bold text-gray-800 hover:text-sage-600 transition-colors">
                                {{ $booking->clinic->name }}
                            </a>
                            <p class="text-xs text-gray-500 mt-1">{{ $booking->clinic->city->display_name ?? '' }}</p>
                        </div>
                        <span class="text-xs font-mono bg-gray-100 px-2 py-1 rounded" dir="ltr">{{ $booking->reference_code }}</span>
                    </div>

                    {{-- Patient column: "أنا" for self-bookings, the
                         relative's name + relationship for proxy bookings.
                         Mirrors how the clinic sees it on their side. --}}
                    <div class="mb-2 flex items-center gap-2 text-sm">
                        <span class="text-xs text-gray-500">@lang('site.booking_patient_label')</span>
                        @if($booking->relative)
                            <span class="font-medium text-gray-800">{{ $booking->relative->name }}</span>
                            <span class="text-xs bg-sage-100 text-sage-700 px-2 py-0.5 rounded-full">
                                {{ $booking->relative->relationship_type === 'other' ? ($booking->relative->relationship_label ?: __('site.relative_type_other')) : __('site.relative_type.' . $booking->relative->relationship_type) }}
                            </span>
                        @else
                            <span class="font-medium text-gray-800">@lang('site.booking_patient_self')</span>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <span class="inline-flex items-center gap-1.5 text-gray-500">
                            <x-icon name="clock" class="w-4 h-4" /> {{ $booking->created_at->diffForHumans() }}
                        </span>
                        @if($booking->service)
                            <span class="text-gray-700">{{ $booking->service->name }}</span>
                        @endif
                        <span class="ms-auto bg-{{ ['new' => 'blue', 'contacted' => 'amber', 'appointment_set' => 'teal', 'completed' => 'green', 'cancelled' => 'red', 'no_show' => 'red'][$booking->status] ?? 'gray' }}-100 text-{{ ['new' => 'blue', 'contacted' => 'amber', 'appointment_set' => 'teal', 'completed' => 'green', 'cancelled' => 'red', 'no_show' => 'red'][$booking->status] ?? 'gray' }}-700 text-xs px-2 py-1 rounded-full">
                            {{ __('site.booking_status.' . $booking->status) }}
                        </span>
                    </div>

                    {{-- Re-book: reopens the booking form for the same clinic with the service pre-selected --}}
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <a href="{{ route('clinic.book.form', ['slug' => $booking->clinic->slug] + ($booking->service_id ? ['service' => $booking->service_id] : [])) }}"
                           class="inline-flex items-center gap-1.5 text-sm text-sage-600 hover:text-sage-700 font-medium">
                            <x-icon name="refresh" class="w-4 h-4" /> @lang('site.rebook')
                        </a>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-xl shadow-sm p-10 text-center">
                    <x-icon name="clipboard" class="w-16 h-16 mx-auto mb-3 text-gray-300" />
                    <p class="text-gray-500 mb-4">@lang('site.account_no_bookings')</p>
                    <a href="{{ route('search') }}" class="bg-sage-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-sage-700 transition-colors inline-block">
                        @lang('site.account_start_browsing')
                    </a>
                </div>
            @endforelse

            @if($bookings->hasPages())
                <div class="mt-4">{{ $bookings->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
