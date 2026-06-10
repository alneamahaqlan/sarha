{{-- Map block — single marker for the linked complex (reuses the Leaflet partial). --}}
@if($clinic && $clinic->latitude && $clinic->longitude)
    @php
        $mapClinics = [[
            'id'         => $clinic->id,
            'name'       => $clinic->name,
            'slug'       => $clinic->slug,
            'lat'        => (float) $clinic->latitude,
            'lng'        => (float) $clinic->longitude,
            'city'       => $clinic->city?->name,
            'directions' => $clinic->directionsUrl(),
        ]];
    @endphp
    <section class="max-w-5xl mx-auto px-4 py-12">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">{{ $cfg['heading'] ?? __('site.lp_location_title') }}</h2>
        @include('public.partials.map', ['mapClinics' => $mapClinics, 'mapId' => 'lp-map-' . $block->id])
    </section>
@elseif($clinic)
    <section class="max-w-5xl mx-auto px-4 py-12 text-center">
        <a href="{{ $clinic->directionsUrl() }}" target="_blank" rel="noopener" data-lp-track="click" data-lp-button="directions"
           class="inline-flex items-center gap-2 bg-sage-600 text-white font-semibold px-6 py-3 rounded-full hover:bg-sage-700 transition">
            @lang('site.lp_directions')
        </a>
    </section>
@endif
