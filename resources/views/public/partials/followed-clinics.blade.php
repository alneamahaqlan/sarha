{{-- Personalised strip: the complexes the signed-in customer follows.
     Hidden entirely for guests / non-followers (empty collection). --}}
@php $followedClinics = $followedClinics ?? collect(); @endphp
@if($followedClinics->isNotEmpty())
<section class="py-12 px-4 bg-white">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-end justify-between mb-8">
            <div>
                <p class="reveal text-sm font-semibold tracking-widest text-sage-600 uppercase mb-2">@lang('site.followed_eyebrow')</p>
                <h2 class="reveal font-display text-3xl font-bold text-charcoal" style="--reveal-delay:80ms">
                    @lang('site.followed_clinics_title')
                </h2>
                <p class="reveal text-gray-500 text-sm mt-1" style="--reveal-delay:140ms">@lang('site.followed_clinics_subtitle')</p>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($followedClinics as $i => $clinic)
                <div class="reveal" style="--reveal-delay:{{ ($i % 4) * 90 }}ms">
                    @include('public.partials.clinic-card', ['clinic' => $clinic, 'badgeContext' => $followedClinics])
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
