{{-- Doctors block — showcase from the linked complex. --}}
@if($clinic && $clinic->doctors->isNotEmpty())
    <section class="max-w-5xl mx-auto px-4 py-12">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">{{ $cfg['heading'] ?? __('site.tab_doctors') }}</h2>
        @include('public.partials.doctors', ['clinic' => $clinic])
    </section>
@endif
