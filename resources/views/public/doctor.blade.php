@extends('layouts.public')

@section('title', $doctor->name . ' — ' . $clinic->name)
@section('description', Str::limit($doctor->bio ?? ($doctor->specialty ?? $clinic->name), 160))
@section('og_image', $doctor->photo_url ?: asset('images/og-default.png'))

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-500 mb-6 flex items-center gap-2 flex-wrap">
        <a href="{{ route('home') }}" class="hover:text-sage-600">@lang('site.breadcrumb_home')</a>
        <span>/</span>
        <a href="{{ route('clinic.show', $clinic->slug) }}" class="hover:text-sage-600">{{ $clinic->name }}</a>
        <span>/</span>
        <span class="text-gray-700">{{ $doctor->name }}</span>
    </nav>

    <header class="flex flex-col sm:flex-row gap-6 items-start">
        <div class="w-28 h-28 rounded-2xl bg-sage-100 overflow-hidden flex items-center justify-center shrink-0">
            @if($doctor->photo_url)
                <img src="{{ $doctor->photo_url }}" alt="{{ $doctor->name }}" class="w-full h-full object-cover">
            @else
                <x-icon name="user" class="w-12 h-12 text-sage-500" />
            @endif
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ $doctor->name }}</h1>
            @if($doctor->specialty)
                <p class="text-sage-600 font-medium mt-1">{{ $doctor->specialty }}</p>
            @endif
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500">
                @if($doctor->years_experience)
                    <span>{{ $doctor->years_experience }} @lang('site.doctor_years_experience')</span>
                @endif
                @if($doctor->university)
                    <span>@lang('site.doctor_university'): {{ $doctor->university }}</span>
                @endif
                @if($doctor->languages)
                    <span>@lang('site.doctor_languages'): {{ $doctor->languages }}</span>
                @endif
            </div>
            @if($doctor->bio)
                <p class="text-gray-600 leading-relaxed mt-4 whitespace-pre-line">{{ $doctor->bio }}</p>
            @endif
        </div>
    </header>

    {{-- Associated complex / clinic --}}
    <section class="mt-8">
        <h2 class="text-lg font-bold text-gray-800 mb-3">@lang('site.detail_doctor_clinics')</h2>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('clinic.show', $clinic->slug) }}" class="inline-flex items-center gap-2 bg-white ring-1 ring-gray-100 hover:shadow rounded-xl px-4 py-3 text-sm">
                <x-icon name="building" class="w-5 h-5 text-sage-500" />
                <span class="font-semibold text-gray-800">{{ $clinic->name }}</span>
            </a>
            @if($doctor->subClinic)
                <a href="{{ route('subclinic.show', ['slug' => $clinic->slug, 'subClinic' => $doctor->subClinic->id]) }}"
                   class="inline-flex items-center gap-2 bg-white ring-1 ring-gray-100 hover:shadow rounded-xl px-4 py-3 text-sm">
                    <x-icon name="building" class="w-5 h-5 text-sage-500" />
                    <span class="font-semibold text-gray-800">{{ $doctor->subClinic->display_name }}</span>
                </a>
            @endif
        </div>
    </section>

    {{-- Optional: services in the doctor's specialty area. --}}
    @if($services->isNotEmpty())
        <section class="mt-8">
            <h2 class="text-lg font-bold text-gray-800 mb-3">@lang('site.detail_section_services')</h2>
            <div class="grid sm:grid-cols-2 gap-3">
                @foreach($services as $service)
                    <div class="bg-white rounded-xl ring-1 ring-gray-100 p-4 flex items-center justify-between">
                        <span class="text-gray-800">{{ $service->name }}</span>
                        @if($service->price !== null)
                            <span class="text-sage-700 font-semibold">{{ number_format((float) $service->price) }} <span class="text-xs">@lang('site.currency_sar')</span></span>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Similar doctors from other complexes. --}}
    @include('public.partials.similar-cards', [
        'items' => $similarDoctors,
        'type' => 'doctor',
        'title' => __('site.similar_doctors'),
        'subtitle' => __('site.similar_from_other_complexes'),
    ])
</div>
@endsection
