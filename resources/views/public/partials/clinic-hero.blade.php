{{--
    Clinic public-profile HERO, extracted from clinic.blade.php so it can be
    reused verbatim as a landing-page header section.
    Expects: $clinic (Clinic model) loaded with city, categories, stories,
        workingHours, googleReviews avg+count, and followers/customers/bookings
        counts; plus the is_verified_badge attribute set by FeatureGate.
    Optional: $showVisit (bool) — render a "visit the full clinic page" button
        (used on landing pages where this hero stands in for the clinic page).
    NOTE: the caller must also include 'public.partials.clinic-hero-scripts'
    once on the page (clinic.blade.php + the landing chrome-header both do).
--}}
@php
    $storyItems = $clinic->stories->map(fn ($s) => [
        'id'      => $s->id,
        'url'     => $s->image ? \Illuminate\Support\Facades\Storage::url($s->image) : null,
        'caption' => $s->caption,
    ])->filter(fn ($s) => $s['url'])->values();
    $hasStories = $storyItems->isNotEmpty();
    $showVisit  = $showVisit ?? false;
@endphp

@once
@push('head')
<style>
    /* Collapsible specialties chip — hide the native disclosure triangle and
       give the expanded list a smooth, modern reveal. Self-contained here so it
       works wherever the hero renders (clinic page + landing-page header). */
    .cats-details > summary { list-style: none; }
    .cats-details > summary::-webkit-details-marker { display: none; }
    .cats-details[open] .cat-chevron { transform: rotate(180deg); }
    .cats-details[open] .cats-reveal { animation: catsReveal .28s ease-out; }
    @keyframes catsReveal {
        from { opacity: 0; transform: translateY(-6px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @media (prefers-reduced-motion: reduce) {
        .cats-details[open] .cats-reveal { animation: none; }
    }
</style>
@endpush
@endonce
<div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6" x-data="storyViewer(@js($storyItems))">
    <div class="p-6">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex items-start gap-4 w-full sm:flex-1 min-w-0">
                {{-- Instagram-style circular logo avatar. When the clinic has live
                     stories, the avatar gets the gradient "story ring" and opens
                     the story viewer on tap. --}}
                @if($hasStories)
                    <button type="button" @click="show(0)" title="@lang('site.stories_view')"
                            class="flex-shrink-0 rounded-full p-[3px] bg-gradient-to-tr from-amber-400 via-pink-500 to-purple-600">
                        <span class="block rounded-full p-[2px] bg-white">
                            @if($clinic->logo)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($clinic->logo) }}" alt="{{ $clinic->name }}"
                                     fetchpriority="high" decoding="async"
                                     class="block w-16 h-16 sm:w-24 sm:h-24 rounded-full object-cover">
                            @else
                                <span class="block w-16 h-16 sm:w-24 sm:h-24 rounded-full bg-sage-50 text-sage-600 flex items-center justify-center text-3xl font-bold">{{ mb_substr($clinic->name, 0, 1) }}</span>
                            @endif
                        </span>
                    </button>
                @elseif($clinic->logo)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($clinic->logo) }}" alt="{{ $clinic->name }}"
                         fetchpriority="high" decoding="async"
                         class="w-16 h-16 sm:w-24 sm:h-24 rounded-full object-cover ring-4 ring-white shadow-md flex-shrink-0">
                @else
                    <span class="w-16 h-16 sm:w-24 sm:h-24 rounded-full bg-sage-50 text-sage-600 flex items-center justify-center text-3xl font-bold ring-4 ring-white shadow-md flex-shrink-0">
                        {{ mb_substr($clinic->name, 0, 1) }}
                    </span>
                @endif
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <h1 class="text-2xl font-bold text-gray-900">{{ $clinic->name }}</h1>
                    {{-- Per product decision, only the admin "featured" (مميزة)
                         badge shows in the header. Verified / premium / open-closed
                         badges are intentionally hidden here — their data still
                         exists and drives ranking, gating and search. --}}
                    @if($clinic->is_featured)
                        <span class="inline-flex items-center gap-1 bg-gold-whisper text-gold-deep px-2 py-0.5 rounded-full text-xs font-semibold"><x-icon name="star-solid" class="w-3.5 h-3.5" /> @lang('site.featured')</span>
                    @endif
                    {{-- Badges Center chips (admin-managed: manual + automatic
                         rules like "most booked" / "fastest growing"). --}}
                    @foreach(app(\App\Services\ClinicBadgeService::class)->forClinic($clinic) as $b)
                        @include('public.partials.badge-chip', ['badge' => $b])
                    @endforeach
                    @include('public.partials.impressions-badge', ['clinic' => $clinic])
                </div>

                {{-- Instagram-style tallies: bold number stacked over a muted
                     label, equal columns split by thin dividers. Followers
                     always shows; customers/bookings stay hidden below the
                     PUBLIC_STAT_MIN_DISPLAY threshold (launch-stage privacy). --}}
                @php
                    $statMin = \App\Models\Clinic::PUBLIC_STAT_MIN_DISPLAY;
                    $tallies = array_values(array_filter([
                        ['value' => $clinic->followers_count ?? 0, 'label' => __('site.followers'),     'show' => true],
                        ['value' => $clinic->customers_count ?? 0, 'label' => __('site.customers'),      'show' => ($clinic->customers_count ?? 0) >= $statMin],
                        ['value' => $clinic->bookings_count ?? 0,  'label' => __('site.bookings_made'),  'show' => ($clinic->bookings_count ?? 0) >= $statMin],
                    ], fn ($t) => $t['show']));
                @endphp
                <div class="mt-4 inline-flex items-center rounded-xl bg-sage-50/70 px-2 py-2">
                    @foreach($tallies as $i => $t)
                        @if($i > 0)
                            <span class="mx-1 h-8 w-px bg-sage-200/80"></span>
                        @endif
                        <div class="px-4 text-center">
                            <div class="text-lg font-bold leading-none text-gray-900">{{ number_format($t['value']) }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $t['label'] }}</div>
                        </div>
                    @endforeach
                </div>

                {{-- Specialties — collapse into one elegant chip and expand on
                     click (smooth <details> reveal), collapsed by default on all
                     screens. The expanded chips double as specialty filters
                     ($store.spec) so the click-to-filter behaviour is preserved. --}}
                @if($clinic->categories->isNotEmpty())
                    <details class="group mt-3 cats-details"
                             x-data
                             x-init="$store.spec.names = @js($clinic->categories->pluck('display_name', 'id'))">
                        <summary class="cats-summary inline-flex w-fit cursor-pointer select-none items-center gap-1.5 rounded-full bg-sage-50 px-3.5 py-1.5 text-sm font-medium text-sage-700 transition-colors hover:bg-sage-100">
                            <span>{{ __('site.specialties_count', ['count' => number_format($clinic->categories->count())]) }}</span>
                            <x-icon name="chevron-down" class="cat-chevron w-3.5 h-3.5 text-sage-500 transition-transform duration-300" />
                        </summary>
                        <div class="cats-reveal flex flex-wrap items-center gap-2 pt-3">
                            <button type="button" @click="$store.spec.clear()"
                                    :class="!$store.spec.active ? 'bg-sage-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium transition-colors">
                                @lang('site.clinic_spec_filter_all')
                            </button>
                            @foreach($clinic->categories as $cat)
                                <button type="button" @click="$store.spec.toggle({{ $cat->id }})"
                                        :class="$store.spec.isActive({{ $cat->id }}) ? 'bg-sage-600 text-white ring-2 ring-sage-400' : 'bg-sage-50 text-sage-700 hover:bg-sage-100'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm transition-colors">
                                    <x-category-icon :emoji="$cat->emoji" :icon="$cat->icon" class="w-4 h-4" /> {{ $cat->display_name }}
                                </button>
                            @endforeach
                        </div>
                    </details>
                @endif

                {{-- Rating + Location — placed below the specialties per design. --}}
                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 mt-3">
                    @if(($clinic->google_reviews_count ?? 0) > 0)
                        <span class="flex items-center gap-1">
                            <span class="text-yellow-500">★</span>
                            <span class="font-semibold text-gray-800">{{ number_format($clinic->google_reviews_avg_rating, 1) }}</span>
                            <span>({{ __('site.reviews_count_label', ['count' => $clinic->google_reviews_count]) }})</span>
                        </span>
                    @endif
                    @php $heroDirections = $clinic->directionsUrl(); @endphp
                    <a @if($heroDirections) href="{{ $heroDirections }}" target="_blank" rel="noopener" data-track="directions" data-clinic="{{ $clinic->id }}" @endif
                       class="flex items-center gap-1 {{ $heroDirections ? 'hover:text-sage-600 transition-colors' : 'pointer-events-none' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {{ $clinic->city->display_name ?? '' }}
                        @if($clinic->address) — {{ $clinic->address }} @endif
                    </a>
                </div>
            </div>
            </div>{{-- /avatar + name flex wrapper --}}

            {{-- Follow + Favorite + Social + Book/Visit CTA. Full-width on
                 mobile so the primary actions are reachable; right-aligned on sm+. --}}
            <div class="flex flex-col gap-3 items-stretch w-full sm:w-auto sm:items-end">
                <div class="flex items-center gap-2 flex-wrap justify-start sm:justify-end">
                    @php $isFollowing = auth('web')->check() && auth('web')->user()->isFollowing($clinic); @endphp
                    <form method="POST" action="{{ route('clinic.follow.toggle', $clinic->slug) }}">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 min-h-touch px-4 py-2 rounded-full text-sm font-semibold transition-colors {{ $isFollowing ? 'bg-sage-600 text-white hover:bg-sage-700' : 'bg-white text-sage-700 ring-1 ring-sage-300 hover:bg-sage-50' }}">
                            <x-icon :name="$isFollowing ? 'check-circle' : 'user-plus'" class="w-4 h-4" />
                            {{ $isFollowing ? __('site.following') : __('site.follow') }}
                        </button>
                    </form>
                    @auth('web')
                        @php $isFavorited = auth('web')->user()->hasFavorited($clinic); @endphp
                        <form method="POST" action="{{ route('favorites.toggle', $clinic->slug) }}" class="js-save-form">
                            @csrf
                            <button type="submit"
                                    data-fav-toggle
                                    data-saved="{{ $isFavorited ? '1' : '0' }}"
                                    data-class-on="bg-red-50 text-red-500 hover:bg-red-100"
                                    data-class-off="bg-gray-100 text-gray-500 hover:bg-gray-200"
                                    data-title-on="{{ __('site.favorite_remove') }}"
                                    data-title-off="{{ __('site.favorite_add') }}"
                                    title="{{ $isFavorited ? __('site.favorite_remove') : __('site.favorite_add') }}"
                                    class="w-11 h-11 rounded-full {{ $isFavorited ? 'bg-red-50 text-red-500 hover:bg-red-100' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }} flex items-center justify-center transition-colors">
                                <span data-fav-icon-on class="{{ $isFavorited ? '' : 'hidden' }}"><x-icon name="heart-solid" class="w-4 h-4" /></span>
                                <span data-fav-icon-off class="{{ $isFavorited ? 'hidden' : '' }}"><x-icon name="heart" class="w-4 h-4" /></span>
                            </button>
                        </form>
                    @endauth
                    @include('public.partials.social-bar', ['variant' => 'inline'])
                </div>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:justify-end">
                    @if($showVisit)
                        <a href="{{ route('clinic.show', $clinic->slug) }}"
                           class="inline-flex items-center justify-center min-h-touch gap-1.5 w-full sm:w-auto bg-white text-sage-700 ring-1 ring-sage-300 hover:bg-sage-50 px-5 py-2.5 rounded-lg font-semibold transition-colors whitespace-nowrap">
                            <x-icon name="building" class="w-4 h-4" />
                            @lang('site.lp_visit_clinic')
                        </a>
                    @endif
                    <a href="{{ route('clinic.book.form', $clinic->slug) }}"
                       data-track="booking" data-clinic="{{ $clinic->id }}"
                       class="inline-flex items-center justify-center min-h-touch w-full sm:w-auto bg-sage-600 hover:bg-sage-700 text-white px-6 py-2.5 rounded-lg font-semibold transition-colors whitespace-nowrap">
                        @lang('site.book_appointment')
                    </a>
                </div>
            </div>
        </div>

        {{-- Unified action bar: Call · WhatsApp · Directions --}}
        <div class="mt-5 pt-5 border-t border-gray-100">
            @include('public.partials.action-bar')
        </div>
    </div>

    {{-- Instagram-style full-screen story viewer (Alpine scope = this hero card). --}}
    @if($hasStories)
        @include('public.partials.story-viewer', ['clinic' => $clinic])
    @endif
</div>
