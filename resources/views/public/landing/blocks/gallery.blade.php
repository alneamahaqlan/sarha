{{-- Gallery block — before/after cases from the linked complex. --}}
@if($clinic && $clinic->beforeAfterPhotos->isNotEmpty())
    <section class="max-w-5xl mx-auto px-4 py-12">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">{{ $cfg['heading'] ?? __('site.tab_before_after') }}</h2>
        @include('public.partials.before-after', ['clinic' => $clinic])
    </section>
@endif
