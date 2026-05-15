@extends('layouts.public')

@section('title', $clinic->name)
@section('description', Str::limit($clinic->description, 160))

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-500 mb-6">
        <a href="{{ route('home') }}" class="hover:text-teal-600">@lang('site.breadcrumb_home')</a>
        <span class="mx-2">/</span>
        <a href="{{ route('search') }}" class="hover:text-teal-600">@lang('site.breadcrumb_clinics')</a>
        <span class="mx-2">/</span>
        <span class="text-gray-800">{{ $clinic->name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Header Card --}}
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                @if($clinic->logo)
                    <div class="h-56 bg-gray-100">
                        <img src="{{ Storage::url($clinic->logo) }}" alt="{{ $clinic->name }}" class="w-full h-full object-cover">
                    </div>
                @endif
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 mb-1">{{ $clinic->name }}</h1>
                            <div class="flex items-center gap-2 text-gray-500 text-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                </svg>
                                {{ $clinic->city->display_name ?? '' }}
                                @if($clinic->address)
                                    — {{ $clinic->address }}
                                @endif
                            </div>
                        </div>
                        @if($clinic->is_featured)
                            <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-sm font-medium">⭐ @lang('site.featured')</span>
                        @endif
                    </div>

                    @if($clinic->categories->isNotEmpty())
                        <div class="flex flex-wrap gap-2 mt-4">
                            @foreach($clinic->categories as $cat)
                                <span class="bg-teal-50 text-teal-700 px-3 py-1 rounded-full text-sm">
                                    {{ $cat->emoji ?? '' }} {{ $cat->display_name }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Description --}}
            @if($clinic->description)
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-bold text-gray-800 mb-3">@lang('site.about_clinic')</h2>
                    <p class="text-gray-600 leading-relaxed">{{ $clinic->description }}</p>
                </div>
            @endif

            {{-- Services --}}
            @if($clinic->services->isNotEmpty())
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-bold text-gray-800 mb-4">@lang('site.services_and_prices')</h2>
                    <div class="divide-y divide-gray-100">
                        @foreach($clinic->services as $service)
                            <div class="py-3 flex items-center justify-between">
                                <div>
                                    <p class="font-medium text-gray-800">{{ $service->name }}</p>
                                    @if($service->description)
                                        <p class="text-sm text-gray-500 mt-0.5">{{ $service->description }}</p>
                                    @endif
                                </div>
                                <div class="text-end ms-4">
                                    @if($service->price)
                                        <span class="text-teal-700 font-bold">
                                            @if($service->old_price)
                                                <span class="line-through text-gray-400 text-sm me-1">{{ number_format($service->old_price) }}</span>
                                            @endif
                                            {{ number_format($service->price) }}
                                            <span class="text-xs font-normal">@lang('site.currency_sar')</span>
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-sm">@lang('site.call_for_inquiry')</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Articles --}}
            @if($clinic->articles->isNotEmpty())
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-bold text-gray-800 mb-4">@lang('site.clinic_articles')</h2>
                    <div class="space-y-3">
                        @foreach($clinic->articles as $article)
                            <div class="border border-gray-100 rounded-lg p-4">
                                <h3 class="font-semibold text-gray-800 mb-1">{{ $article->title }}</h3>
                                <p class="text-sm text-gray-500">{{ Str::limit($article->excerpt ?? strip_tags($article->body ?? ''), 120) }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-5">

            {{-- Contact Info --}}
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="font-bold text-gray-800 mb-4">@lang('site.contact_info')</h3>
                <div class="space-y-3">
                    @if($clinic->phone)
                        <a href="tel:{{ $clinic->phone }}" class="flex items-center gap-3 text-gray-700 hover:text-teal-600">
                            <span class="bg-teal-50 p-2 rounded-lg">📞</span>
                            <span dir="ltr">{{ $clinic->phone }}</span>
                        </a>
                    @endif
                    @if($clinic->email)
                        <div class="flex items-center gap-3 text-gray-700">
                            <span class="bg-teal-50 p-2 rounded-lg">✉️</span>
                            <span class="text-sm" dir="ltr">{{ $clinic->email }}</span>
                        </div>
                    @endif
                    @if($clinic->instagram)
                        <a href="https://instagram.com/{{ $clinic->instagram }}" target="_blank" class="flex items-center gap-3 text-gray-700 hover:text-pink-600">
                            <span class="bg-pink-50 p-2 rounded-lg">📸</span>
                            <span class="text-sm" dir="ltr">@{{ $clinic->instagram }}</span>
                        </a>
                    @endif
                    @if($clinic->twitter)
                        <div class="flex items-center gap-3 text-gray-700">
                            <span class="bg-blue-50 p-2 rounded-lg">🐦</span>
                            <span class="text-sm" dir="ltr">{{ $clinic->twitter }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Booking Form --}}
            <div class="bg-white rounded-xl shadow-sm p-6" id="booking-form">
                <h3 class="font-bold text-gray-800 mb-4">@lang('site.book_appointment')</h3>

                @if($errors->any())
                    <div class="bg-red-50 text-red-700 rounded-lg p-3 text-sm mb-4">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('clinic.book', $clinic->slug) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.full_name')</label>
                        <input type="text" name="customer_name" value="{{ old('customer_name', auth('web')->user()?->name) }}"
                               required class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.phone_number')</label>
                        <input type="tel" name="customer_phone" value="{{ old('customer_phone', auth('web')->user()?->phone) }}"
                               required class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400" dir="ltr">
                    </div>
                    @if($clinic->services->isNotEmpty())
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.requested_service')</label>
                            <select name="service_id" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">
                                <option value="">@lang('site.not_specified')</option>
                                @foreach($clinic->services as $svc)
                                    <option value="{{ $svc->id }}">{{ $svc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.notes_optional')</label>
                        <textarea name="notes" rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400">{{ old('notes') }}</textarea>
                    </div>
                    <button type="submit" class="w-full bg-teal-600 text-white py-3 rounded-lg font-semibold hover:bg-teal-700 transition-colors">
                        @lang('site.submit_booking')
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
