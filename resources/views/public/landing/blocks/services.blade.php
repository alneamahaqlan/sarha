{{-- Services block — auto (from complex) or a manually-picked subset. --}}
@if($clinic && $clinic->services->isNotEmpty())
    @php
        $limit  = (int) ($cfg['item_limit'] ?? 6);
        $source = $cfg['source'] ?? 'auto';
        $manualIds = array_map('intval', (array) ($cfg['manual_ids'] ?? []));

        // The eager-loaded services include the "خدمات أخرى" catch-all (it
        // must appear in the booking dropdown), so reject it from this showcase.
        $showcase = $clinic->services->reject(fn ($s) => $s->is_catchall)->values();

        $services = ($source === 'manual' && $manualIds)
            ? collect($manualIds)->map(fn ($id) => $showcase->firstWhere('id', $id))->filter()->values()
            : $showcase->take($limit);

        $heading = $cfg['heading'] ?? __('site.tab_services');
    @endphp
    @if($services->isNotEmpty())
        <section class="max-w-5xl mx-auto px-4 py-12">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">{{ $heading }}</h2>
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($services as $service)
                    @include('public.partials.service-row', ['service' => $service, 'clinic' => $clinic])
                @endforeach
            </div>
        </section>
    @endif
@endif
