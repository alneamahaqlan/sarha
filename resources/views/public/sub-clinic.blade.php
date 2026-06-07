@extends('layouts.public')

@section('title', $subClinic->display_name . ' — ' . $clinic->name)
@section('description', Str::limit($subClinic->description ?? $clinic->name, 160))

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-500 mb-6 flex items-center gap-2 flex-wrap">
        <a href="{{ route('home') }}" class="hover:text-sage-600">@lang('site.breadcrumb_home')</a>
        <span>/</span>
        <a href="{{ route('clinic.show', $clinic->slug) }}" class="hover:text-sage-600">{{ $clinic->name }}</a>
        <span>/</span>
        <span class="text-gray-700">{{ $subClinic->display_name }}</span>
    </nav>

    <header class="mb-8">
        @if($subClinic->category)
            <span class="text-sm text-gray-500">{{ $subClinic->category->emoji }} {{ $subClinic->category->name }}</span>
        @endif
        <div class="flex items-center gap-2 flex-wrap mt-1">
            <h1 class="text-2xl font-bold text-gray-800">{{ $subClinic->display_name }}</h1>
            @include('public.partials.impressions-badge', ['clinic' => $clinic])
        </div>
        @if($subClinic->description)
            <p class="text-gray-600 leading-relaxed mt-3 whitespace-pre-line">{{ $subClinic->description }}</p>
        @endif
    </header>

    {{-- Sections, in spec order: offers → packages → services → doctors. --}}
    @if($offers->isNotEmpty())
        <section class="mb-10">
            <h2 class="text-lg font-bold text-gray-800 mb-4">@lang('site.detail_section_offers')</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($offers as $offer)
                    @include('public.partials.offer-card', ['offer' => $offer, 'clinic' => $clinic, 'large' => false])
                @endforeach
            </div>
        </section>
    @endif

    @if($packages->isNotEmpty())
        <section class="mb-10">
            <h2 class="text-lg font-bold text-gray-800 mb-4">@lang('site.detail_section_packages')</h2>
            <ul class="space-y-2">
                @foreach($packages as $package)
                    <li class="bg-white rounded-xl ring-1 ring-gray-100 p-4 flex items-center justify-between">
                        <span class="font-semibold text-gray-800">{{ $package->name }}</span>
                        @if($package->price !== null)
                            <span class="text-sage-700 font-bold">{{ number_format((float) $package->price) }} <span class="text-xs"><x-riyal /></span></span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($services->isNotEmpty())
        <section class="mb-10">
            <h2 class="text-lg font-bold text-gray-800 mb-4">@lang('site.detail_section_services')</h2>
            <div class="grid sm:grid-cols-2 gap-3">
                @foreach($services as $service)
                    <div class="bg-white rounded-xl ring-1 ring-gray-100 p-4 flex items-center justify-between">
                        <span class="text-gray-800">{{ $service->name }}</span>
                        @if($service->price !== null)
                            <span class="text-sage-700 font-semibold">{{ number_format((float) $service->price) }} <span class="text-xs"><x-riyal /></span></span>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if($doctors->isNotEmpty())
        <section class="mb-10">
            <h2 class="text-lg font-bold text-gray-800 mb-4">@lang('site.detail_section_doctors')</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach($doctors as $doctor)
                    <a href="{{ route('doctor.show', ['slug' => $clinic->slug, 'doctor' => $doctor->id]) }}"
                       class="group bg-white rounded-xl ring-1 ring-gray-100 hover:shadow-lg transition-all p-4 text-center">
                        <div class="w-16 h-16 mx-auto rounded-full bg-sage-100 overflow-hidden flex items-center justify-center">
                            @if($doctor->photo_url)
                                <img src="{{ $doctor->photo_url }}" alt="{{ $doctor->name }}" class="w-full h-full object-cover">
                            @else
                                <x-icon name="user" class="w-7 h-7 text-sage-500" />
                            @endif
                        </div>
                        <p class="mt-2 text-sm font-semibold text-gray-800 group-hover:text-sage-700 line-clamp-1">{{ $doctor->name }}</p>
                        @if($doctor->specialty)
                            <p class="text-xs text-gray-500 line-clamp-1">{{ $doctor->specialty }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Similar clinics from other complexes. --}}
    @include('public.partials.similar-cards', [
        'items' => $similarSubClinics,
        'type' => 'subClinic',
        'title' => __('site.similar_subclinics'),
        'subtitle' => __('site.similar_from_other_complexes'),
    ])
</div>
@endsection
