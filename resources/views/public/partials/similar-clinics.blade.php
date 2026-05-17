{{--
    Similar clinics block.
    Expects: $similarClinics collection (can be empty)
--}}
@if(($similarClinics ?? collect())->isNotEmpty())
<section class="mt-12">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">@lang('site.similar_clinics')</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($similarClinics as $clinic)
            @include('public.partials.clinic-card', ['clinic' => $clinic])
        @endforeach
    </div>
</section>
@endif
