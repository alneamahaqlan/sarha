{{-- Gallery block — auto (from complex) or a manually-picked subset. --}}
@if($clinic && $clinic->beforeAfterPhotos->isNotEmpty())
    @php
        $limit  = (int) ($cfg['item_limit'] ?? 8);
        $source = $cfg['source'] ?? 'auto';
        $manualIds = array_map('intval', (array) ($cfg['manual_ids'] ?? []));

        $photos = ($source === 'manual' && $manualIds)
            ? collect($manualIds)->map(fn ($id) => $clinic->beforeAfterPhotos->firstWhere('id', $id))->filter()->values()
            : $clinic->beforeAfterPhotos->take($limit);

        $clinicForBlock = clone $clinic;
        $clinicForBlock->setRelation('beforeAfterPhotos', $photos);
    @endphp
    @if($photos->isNotEmpty())
        <section class="max-w-5xl mx-auto px-4 py-12">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">{{ $cfg['heading'] ?? __('site.tab_before_after') }}</h2>
            @include('public.partials.before-after', ['clinic' => $clinicForBlock])
        </section>
    @endif
@endif
