@extends('layouts.public')

@section('title', __('site.account_my_bookings'))

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">@lang('site.account_my_bookings')</h1>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-1">@include('public.account._nav')</div>

        <div class="lg:col-span-3 space-y-3">
            @forelse($bookings as $booking)
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <div class="flex items-start justify-between mb-3 gap-4">
                        <div class="flex-1">
                            <a href="{{ route('clinic.show', $booking->clinic->slug) }}"
                               class="font-bold text-gray-800 hover:text-teal-600 transition-colors">
                                {{ $booking->clinic->name }}
                            </a>
                            <p class="text-xs text-gray-500 mt-1">{{ $booking->clinic->city->display_name ?? '' }}</p>
                        </div>
                        <span class="text-xs font-mono bg-gray-100 px-2 py-1 rounded" dir="ltr">{{ $booking->reference_code }}</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <span class="text-gray-500">
                            🕒 {{ $booking->created_at->diffForHumans() }}
                        </span>
                        @if($booking->service)
                            <span class="text-gray-700">{{ $booking->service->name }}</span>
                        @endif
                        <span class="ms-auto bg-{{ ['new' => 'blue', 'contacted' => 'amber', 'appointment_set' => 'teal', 'completed' => 'green', 'cancelled' => 'red', 'no_show' => 'red'][$booking->status] ?? 'gray' }}-100 text-{{ ['new' => 'blue', 'contacted' => 'amber', 'appointment_set' => 'teal', 'completed' => 'green', 'cancelled' => 'red', 'no_show' => 'red'][$booking->status] ?? 'gray' }}-700 text-xs px-2 py-1 rounded-full">
                            {{ __('admin.status.' . $booking->status) }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-xl shadow-sm p-10 text-center">
                    <div class="text-6xl mb-3">📋</div>
                    <p class="text-gray-500 mb-4">@lang('site.account_no_bookings')</p>
                    <a href="{{ route('search') }}" class="bg-teal-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-teal-700 transition-colors inline-block">
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
