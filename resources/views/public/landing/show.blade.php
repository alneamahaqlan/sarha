@extends('layouts.public')

@php
    $loc = app()->getLocale();
    $en  = $loc === 'en';

    $lpTitle = ($en ? $page->seo_title_en : $page->seo_title_ar)
        ?: ($en ? $page->title_en : $page->title_ar)
        ?: ($page->title_ar ?: __('site.brand'));
    $lpDesc  = ($en ? $page->seo_description_en : $page->seo_description_ar) ?: __('site.meta_description');
    $ogTitle = ($en ? $page->og_title_en : $page->og_title_ar) ?: $lpTitle;
    $ogDesc  = ($en ? $page->og_description_en : $page->og_description_ar) ?: $lpDesc;
    $ogImage = $page->social_image ?: $page->cover_image;
@endphp

@section('title', $lpTitle)
@section('description', $lpDesc)
@section('og_title', $ogTitle)
@section('og_description', $ogDesc)
@if($ogImage)
    @section('og_image', \Illuminate\Support\Facades\Storage::url($ogImage))
@endif
@if($page->canonical_url)
    @section('canonical', $page->canonical_url)
@endif
@if($page->meta_robots && $page->meta_robots !== 'index,follow')
    @section('meta_robots', $page->meta_robots)
@endif

@push('head')
    @php $schema = $page->schema_markup ?: \App\Services\LandingSchemaService::for($page, $clinic ?? null); @endphp
    @if($schema)
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endif
@endpush

@section('content')
    <main class="bg-gray-50 min-h-screen"
          data-lp-id="{{ $page->id }}"
          data-lp-track-root>
        @foreach($blocks as $block)
            @includeIf('public.landing.blocks.' . $block->type, [
                'page'              => $page,
                'block'            => $block,
                'cfg'              => $block->config ?? [],
                'clinic'           => $clinic ?? null,
                'comparisonClinics'=> $comparisonClinics ?? collect(),
            ])

            {{-- Type-specific sections render right after the hero. --}}
            @if($block->type === 'hero')
                @if($page->type === 'comparison' && ($comparisonClinics ?? collect())->isNotEmpty())
                    @include('public.landing.partials.comparison', ['page' => $page, 'comparisonClinics' => $comparisonClinics])
                @elseif(in_array($page->type, ['city', 'category']) && ($listClinics ?? collect())->isNotEmpty())
                    @include('public.landing.partials.clinics-list', ['page' => $page, 'listClinics' => $listClinics])
                @endif
            @endif
        @endforeach
    </main>

    {{-- Floating WhatsApp / call CTAs, reusing the clinic component contract. --}}
    @if($clinic)
        @include('public.partials.floating-actions', ['clinic' => $clinic])
    @endif

    {{-- Behavioral tracking beacons (visit / scroll / dwell / clicks / leave). --}}
    @include('public.landing.partials.tracking', ['page' => $page])
@endsection
