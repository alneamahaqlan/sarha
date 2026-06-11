{{-- Doctors block — auto (from complex) or a manually-picked subset. --}}
@if($clinic && $clinic->doctors->isNotEmpty())
    @php
        $limit  = (int) ($cfg['item_limit'] ?? 6);
        $source = $cfg['source'] ?? 'auto';
        $manualIds = array_map('intval', (array) ($cfg['manual_ids'] ?? []));

        $doctors = ($source === 'manual' && $manualIds)
            ? collect($manualIds)->map(fn ($id) => $clinic->doctors->firstWhere('id', $id))->filter()->values()
            : $clinic->doctors->take($limit);

        // clone so setRelation doesn't mutate the shared clinic used by later blocks.
        $clinicForBlock = clone $clinic;
        $clinicForBlock->setRelation('doctors', $doctors);
    @endphp
    @if($doctors->isNotEmpty())
        <section class="max-w-5xl mx-auto px-4 py-12">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">{{ $cfg['heading'] ?? __('site.tab_doctors') }}</h2>
            @include('public.partials.doctors', ['clinic' => $clinicForBlock])
        </section>
    @endif
@endif
