{{-- Reviews block — Google reviews from the linked complex. --}}
@if($clinic && ($clinic->google_reviews_count ?? 0) > 0)
    <section class="max-w-5xl mx-auto px-4 py-12">
        @include('public.partials.google-reviews', ['clinic' => $clinic])
    </section>
@endif
