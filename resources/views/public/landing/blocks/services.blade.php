{{-- Services block — auto (from complex) or a manually-picked subset. --}}
@if($clinic && $clinic->services->isNotEmpty())
    @php
        $limit  = (int) ($cfg['item_limit'] ?? 6);
        $source = $cfg['source'] ?? 'auto';
        $manualIds = array_map('intval', (array) ($cfg['manual_ids'] ?? []));

        $services = ($source === 'manual' && $manualIds)
            ? collect($manualIds)->map(fn ($id) => $clinic->services->firstWhere('id', $id))->filter()->values()
            : $clinic->services->take($limit);

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
