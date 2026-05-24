@extends('layouts.public')

@section('title', __('site.account_title'))

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">

    {{-- Header --}}
    <div class="bg-gradient-to-r from-sage-600 to-sage-800 text-white rounded-2xl p-6 mb-6">
        <h1 class="text-2xl font-bold mb-2">{{ $user->name ?: '—' }}</h1>
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
        <div class="lg:col-span-1">
            @include('public.account._nav')
        </div>

        <div class="lg:col-span-3">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-5">@lang('site.account_profile')</h2>

                @if(session('success'))
                    <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 text-sm mb-5">
                        {{ session('success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm mb-5">
                        @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('account.update') }}" class="space-y-4 max-w-md">
                    @csrf @method('PATCH')

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.full_name')</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                               class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.phone_number')</label>
                        <input type="tel" value="{{ $user->phone }}" disabled dir="ltr"
                               class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-500">
                        <p class="text-xs text-gray-400 mt-1">@lang('site.account_phone_locked')</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.contact_info')</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-sage-400" dir="ltr">
                    </div>

                    <button type="submit" class="bg-sage-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-sage-700 transition-colors">
                        @lang('site.account_save')
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
