{{-- Personalised strip: the complexes the signed-in customer follows.
     Hidden entirely for guests / non-followers (empty collection). --}}
@php $followedClinics = $followedClinics ?? collect(); @endphp
@if($followedClinics->isNotEmpty())
<section class="py-12 px-4 bg-white">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-end justify-between mb-8 gap-3">
            <div>
                <p class="reveal text-sm font-semibold tracking-widest text-sage-600 uppercase mb-2">@lang('site.followed_eyebrow')</p>
                <h2 class="reveal font-display text-3xl font-bold text-charcoal" style="--reveal-delay:80ms">
                    @lang('site.followed_clinics_title')
                </h2>
                <p class="reveal text-gray-500 text-sm mt-1" style="--reveal-delay:140ms">@lang('site.followed_clinics_subtitle')</p>
            </div>
            <a href="{{ route('account.following') }}" class="reveal hidden sm:inline-flex items-center gap-1 text-sm font-semibold text-sage-600 hover:text-sage-700 whitespace-nowrap shrink-0" style="--reveal-delay:140ms">
                @lang('site.view_all') <span aria-hidden="true">←</span>
            </a>
        </div>
        {{-- 2-up on mobile (was full-width) to match the offers strip; items
             beyond 6 hide until sm, with "عرض المزيد" opening the full page. --}}
        {{-- Warm the badge memo once so each card's forCard() is a memory hit (no N+1). --}}
        @php app(\App\Services\ClinicBadgeService::class)->forClinics($followedClinics->pluck('id')->all(), 'cards'); @endphp
        <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6 items-stretch">
            @foreach($followedClinics as $i => $clinic)
                <div class="reveal {{ $i >= 6 ? 'hidden sm:block' : '' }}" style="--reveal-delay:{{ ($i % 4) * 90 }}ms">
                    @include('public.partials.clinic-card', ['clinic' => $clinic, 'badgeContext' => $followedClinics])
                </div>
            @endforeach
        </div>
        <div class="mt-6 text-center sm:hidden">
            <a href="{{ route('account.following') }}" class="inline-flex items-center justify-center gap-1.5 bg-white border border-sage-200 text-sage-700 hover:bg-sage-50 text-sm font-semibold px-6 py-2.5 rounded-full shadow-sm transition-colors">
                @lang('site.followed_show_more') <span aria-hidden="true">←</span>
            </a>
        </div>
    </div>
</section>
@endif
