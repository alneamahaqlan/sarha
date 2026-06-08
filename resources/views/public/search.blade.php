@extends('layouts.public')

@section('title', __('site.nav_search'))

@section('content')

<div class="max-w-7xl mx-auto px-4 py-8">
    {{-- Search Filters --}}
    <form action="{{ route('search') }}" method="GET" class="bg-white rounded-xl shadow-sm p-5 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="relative md:col-span-4" x-data="searchSuggest" @click.outside="open = false">
                <span class="absolute inset-y-0 start-3 flex items-center text-gray-400 pointer-events-none"><x-icon name="search" class="w-5 h-5" /></span>
                <input
                    type="text"
                    name="q"
                    x-model="term"
                    autocomplete="off"
                    @input.debounce.250ms="fetchSuggest()"
                    @focus="if (hasResults) open = true"
                    placeholder="@lang('site.search_clinic_or_specialty')"
                    class="w-full border border-gray-200 rounded-lg ps-11 pe-4 py-2.5 text-gray-800 focus:outline-none focus:ring-2 focus:ring-sage-400"
                >
                {{-- Suggestions dropdown --}}
                <div x-show="open && hasResults" x-cloak x-transition.opacity
                     class="absolute z-30 mt-1 w-full bg-white rounded-xl shadow-2xl ring-1 ring-gray-100 max-h-96 overflow-auto text-start py-1">
                    <template x-if="clinics.length">
                        <div>
                            <div class="px-3 pt-2 pb-1 text-[11px] font-semibold tracking-wide text-gray-400">@lang('site.suggest_clinics')</div>
                            <template x-for="item in clinics" :key="'c'+item.url">
                                <a :href="item.url" class="flex items-center gap-2 px-3 py-2 hover:bg-sage-50 text-sm text-gray-700">
                                    <x-icon name="building" class="w-4 h-4 text-sage-primary shrink-0" />
                                    <span x-text="item.label"></span>
                                </a>
                            </template>
                        </div>
                    </template>
                    <template x-if="services.length">
                        <div>
                            <div class="px-3 pt-2 pb-1 text-[11px] font-semibold tracking-wide text-gray-400">@lang('site.suggest_services')</div>
                            <template x-for="item in services" :key="'s'+item.label+item.url">
                                <a :href="item.url" class="flex items-center gap-2 px-3 py-2 hover:bg-sage-50 text-sm text-gray-700">
                                    <x-icon name="clipboard" class="w-4 h-4 text-gold-deep shrink-0" />
                                    <span x-text="item.label"></span>
                                    <span class="text-xs text-gray-400 ms-auto" x-text="item.sub"></span>
                                </a>
                            </template>
                        </div>
                    </template>
                    <template x-if="doctors.length">
                        <div>
                            <div class="px-3 pt-2 pb-1 text-[11px] font-semibold tracking-wide text-gray-400">@lang('site.suggest_doctors')</div>
                            <template x-for="item in doctors" :key="'d'+item.label+item.url">
                                <a :href="item.url" class="flex items-center gap-2 px-3 py-2 hover:bg-sage-50 text-sm text-gray-700">
                                    <x-icon name="user" class="w-4 h-4 text-plum-primary shrink-0" />
                                    <span x-text="item.label"></span>
                                    <span class="text-xs text-gray-400 ms-auto" x-text="item.sub"></span>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
            <select name="city" class="md:col-span-2 border border-gray-200 rounded-lg px-3 py-2.5 text-gray-700 focus:outline-none focus:ring-2 focus:ring-sage-400">
                <option value="">@lang('site.search_all_cities')</option>
                @foreach($cities as $city)
                    <option value="{{ $city->id }}" @selected(request('city') == $city->id)>{{ $city->display_name }}</option>
                @endforeach
            </select>
            <select name="category" class="md:col-span-2 border border-gray-200 rounded-lg px-3 py-2.5 text-gray-700 focus:outline-none focus:ring-2 focus:ring-sage-400">
                <option value="">@lang('site.search_all_categories')</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(request('category') == $cat->id)>
                        {{ $cat->display_name }}
                    </option>
                @endforeach
            </select>
            <select name="sort" id="search-sort" class="md:col-span-2 border border-gray-200 rounded-lg px-3 py-2.5 text-gray-700 focus:outline-none focus:ring-2 focus:ring-sage-400">
                <option value="featured" @selected(($sort ?? 'featured') === 'featured')>@lang('site.sort_featured')</option>
                <option value="top_rated" @selected(($sort ?? '') === 'top_rated')>@lang('site.sort_top_rated')</option>
                <option value="cheapest" @selected(($sort ?? '') === 'cheapest')>@lang('site.sort_cheapest')</option>
                <option value="most_booked" @selected(($sort ?? '') === 'most_booked')>@lang('site.sort_most_booked')</option>
                <option value="nearest" @selected(($sort ?? '') === 'nearest')>@lang('site.sort_nearest')</option>
            </select>
            {{-- Preserve geolocation for "nearest" across pagination / re-submits. --}}
            <input type="hidden" name="lat" id="search-lat" value="{{ request('lat') }}">
            <input type="hidden" name="lng" id="search-lng" value="{{ request('lng') }}">
            <button type="submit" class="md:col-span-2 bg-sage-600 text-white rounded-lg py-2.5 font-semibold hover:bg-sage-700 transition-colors">
                @lang('site.search_button')
            </button>
        </div>

        {{-- Secondary filters: district · rating · max price --}}
        <div class="mt-3 flex flex-wrap gap-3">
            @if($districts->isNotEmpty())
                <select name="district" onchange="this.form.submit()" class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-sage-400 min-w-44">
                    <option value="">@lang('site.filter_any_district')</option>
                    @foreach($districts as $d)
                        <option value="{{ $d }}" @selected(request('district') == $d)>{{ $d }}</option>
                    @endforeach
                </select>
            @endif
            <select name="min_rating" onchange="this.form.submit()" class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-sage-400 min-w-40">
                <option value="">@lang('site.filter_rating_any')</option>
                <option value="4.5" @selected(request('min_rating') === '4.5')>★ 4.5+</option>
                <option value="4" @selected(request('min_rating') === '4')>★ 4+</option>
                <option value="3" @selected(request('min_rating') === '3')>★ 3+</option>
            </select>
            <input type="number" name="price_max" value="{{ request('price_max') }}" min="0" step="50"
                   onchange="this.form.submit()" placeholder="@lang('site.filter_price_max')"
                   class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-sage-400 w-44">
        </div>

        {{-- Quick toggles --}}
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <label class="inline-flex items-center gap-2 cursor-pointer select-none rounded-full border px-3.5 py-1.5 text-sm transition-colors {{ request('open_now') ? 'bg-sage-50 border-sage-300 text-sage-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                <input type="checkbox" name="open_now" value="1" @checked(request('open_now')) onchange="this.form.submit()" class="accent-sage-600">
                <span class="inline-flex items-center gap-1.5"><x-icon name="clock" class="w-4 h-4" /> @lang('site.filter_open_now')</span>
            </label>
            <label class="inline-flex items-center gap-2 cursor-pointer select-none rounded-full border px-3.5 py-1.5 text-sm transition-colors {{ request('featured') ? 'bg-gold-whisper border-gold-soft text-gold-deep' : 'border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                <input type="checkbox" name="featured" value="1" @checked(request('featured')) onchange="this.form.submit()" class="accent-gold-primary">
                <span class="inline-flex items-center gap-1.5"><x-icon name="star-solid" class="w-4 h-4" /> @lang('site.featured')</span>
            </label>

            {{-- "Near me" — one-tap geolocation that sorts by distance from the user.
                 Sits next to the existing filter pills so first-time visitors discover
                 the location-based search without changing the sort dropdown. --}}
            <button type="button" id="btn-near-me"
                    class="inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm transition-colors {{ (($sort ?? '') === 'nearest') ? 'bg-blue-50 border-blue-300 text-blue-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                    <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1.07a6.002 6.002 0 014.93 4.93H17a1 1 0 110 2h-1.07a6.002 6.002 0 01-4.93 4.93V17a1 1 0 11-2 0v-1.07A6.002 6.002 0 014.07 11H3a1 1 0 110-2h1.07A6.002 6.002 0 019 4.07V3a1 1 0 011-1zm0 4a4 4 0 100 8 4 4 0 000-8zm0 2a2 2 0 110 4 2 2 0 010-4z" clip-rule="evenodd" />
                </svg>
                @lang('site.filter_near_me')
            </button>
        </div>
    </form>

    {{-- Active filter chips (removable) --}}
    @php
        $cityById = $cities->keyBy('id');
        $catById = $categories->keyBy('id');
        $chips = [];
        if (request('q'))        $chips[] = ['key' => 'q',        'label' => '«' . request('q') . '»'];
        if (request('city') && $cityById->has(request('city')))       $chips[] = ['key' => 'city',     'label' => $cityById[request('city')]->display_name];
        if (request('category') && $catById->has(request('category'))) $chips[] = ['key' => 'category', 'label' => $catById[request('category')]->display_name];
        if (request('district')) $chips[] = ['key' => 'district', 'label' => request('district')];
        if (request('min_rating')) $chips[] = ['key' => 'min_rating', 'label' => __('site.filter_rating_chip', ['n' => request('min_rating')])];
        if (request('price_max')) $chips[] = ['key' => 'price_max', 'label' => __('site.filter_price_chip', ['amount' => number_format((float) request('price_max'))])];
        if (request('featured')) $chips[] = ['key' => 'featured', 'label' => __('site.featured')];
        if (request('open_now')) $chips[] = ['key' => 'open_now', 'label' => __('site.filter_open_now')];

        $base = collect(request()->except('page'))->filter(fn ($v) => $v !== null && $v !== '');
        $removeUrl = function (string $key) use ($base) {
            $rest = $base->except($key)->all();
            return empty($rest) ? route('search', ['clear' => 1]) : route('search', $rest);
        };
    @endphp
    @if(! empty($chips))
        <div class="flex flex-wrap items-center gap-2 mb-5">
            <span class="text-xs text-gray-400">@lang('site.active_filters'):</span>
            @foreach($chips as $chip)
                <a href="{{ $removeUrl($chip['key']) }}"
                   class="group inline-flex items-center gap-1.5 bg-sage-50 text-sage-700 rounded-full ps-3 pe-2 py-1 text-sm hover:bg-sage-100 transition-colors">
                    {{ $chip['label'] }}
                    <span class="w-4 h-4 rounded-full bg-sage-200/70 group-hover:bg-sage-300 flex items-center justify-center text-[11px] leading-none">✕</span>
                </a>
            @endforeach
            <a href="{{ route('search', ['clear' => 1]) }}" class="text-xs text-gray-400 hover:text-red-500 underline ms-1">@lang('site.clear_all')</a>
        </div>
    @endif

    {{-- Map of current results (#60, #61, #62) — rich popup cards with snippet,
         Google rating + reviews count, distance and direct actions. --}}
    @if(($mapClinics ?? collect())->isNotEmpty())
        <div class="mb-8">
            @include('public.partials.map', [
                'mapClinics'     => $mapClinics,
                'mapId'          => 'search-map',
                'showAreaSearch' => true,
                'tall'           => true,
                'userLat'        => $userLat ?? null,
                'userLng'        => $userLng ?? null,
            ])
        </div>
    @endif

    {{-- Results count --}}
    <div class="flex items-end justify-between mb-5">
        <div>
            <h1 class="font-display text-xl font-bold text-charcoal">
                <span class="text-sage-primary">{{ number_format($clinics->total()) }}</span>
                @if(request('q'))
                    {{ __('site.search_count_for', ['query' => request('q')]) }}
                @else
                    {{ __('site.search_count_label') }}
                @endif
            </h1>
        </div>
    </div>

    @if($clinics->isEmpty())
        <div class="text-center py-16 text-gray-500">
            <x-icon name="search" class="w-16 h-16 mx-auto mb-4 text-gray-300" />
            <p class="text-xl font-semibold mb-2">@lang('site.no_results_title')</p>
            <p>@lang('site.no_results_subtitle')</p>
        </div>
    @else
        <div class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3 sm:gap-4">
            @foreach($clinics as $clinic)
                <div class="relative">
                    @auth('web')
                        <form method="POST" action="{{ route('favorites.toggle', $clinic->slug) }}" class="absolute bottom-3 end-3 z-20">
                            @csrf
                            @php $fav = auth('web')->user()->hasFavorited($clinic); @endphp
                            <button type="submit"
                                    title="{{ $fav ? __('site.favorite_remove') : __('site.favorite_add') }}"
                                    aria-label="{{ $fav ? __('site.favorite_remove') : __('site.favorite_add') }}"
                                    class="inline-flex items-center justify-center w-9 h-9 rounded-full shadow-sm ring-1 ring-gray-100 transition-colors {{ $fav ? 'bg-red-50 text-red-500 hover:bg-red-100' : 'bg-white/95 text-gray-400 hover:text-red-500 hover:bg-red-50' }}">
                                <x-icon :name="$fav ? 'heart-solid' : 'heart'" class="w-4 h-4" />
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" title="{{ __('site.saved_login_prompt') }}"
                           class="absolute bottom-3 end-3 z-20 inline-flex items-center justify-center w-9 h-9 rounded-full bg-white/95 text-gray-400 shadow-sm ring-1 ring-gray-100 hover:text-red-500 hover:bg-red-50 transition-colors">
                            <x-icon name="heart" class="w-4 h-4" />
                        </a>
                    @endauth
                    @include('public.partials.clinic-card', ['clinic' => $clinic, 'badgeContext' => $clinics->getCollection(), 'compare' => true])
                </div>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $clinics->links() }}
        </div>
    @endif
</div>

{{-- Progressive geolocation for the "nearest" sort and the "Near me" pill.
     Falls back silently if denied/unsupported. --}}
<script>
(function () {
    var sortEl = document.getElementById('search-sort');
    var latEl  = document.getElementById('search-lat');
    var lngEl  = document.getElementById('search-lng');
    var nearMeBtn = document.getElementById('btn-near-me');
    var form = sortEl ? sortEl.form : (nearMeBtn ? nearMeBtn.closest('form') : null);
    if (!form) return;

    function submitWithLocation() {
        if (sortEl) sortEl.value = 'nearest';

        if (latEl && lngEl && latEl.value && lngEl.value) {
            form.submit();
            return;
        }
        if (!navigator.geolocation) {
            form.submit(); // server falls back to default order without coords.
            return;
        }
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                if (latEl) latEl.value = pos.coords.latitude;
                if (lngEl) lngEl.value = pos.coords.longitude;
                form.submit();
            },
            function () { form.submit(); }, // denied — server falls back gracefully.
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
        );
    }

    if (sortEl) {
        sortEl.addEventListener('change', function () {
            if (sortEl.value === 'nearest') submitWithLocation();
        });
    }
    if (nearMeBtn) {
        nearMeBtn.addEventListener('click', submitWithLocation);
    }
})();
</script>

{{-- Typeahead suggestions (complexes · services · doctors) --}}
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('searchSuggest', () => ({
        term: @json(request('q') ?? ''),
        open: false,
        clinics: [], services: [], doctors: [],
        _ctrl: null,
        get hasResults() { return this.clinics.length + this.services.length + this.doctors.length > 0; },
        async fetchSuggest() {
            const t = this.term.trim();
            if (t.length < 2) { this.clinics = this.services = this.doctors = []; this.open = false; return; }
            if (this._ctrl) this._ctrl.abort();
            this._ctrl = new AbortController();
            try {
                const res = await fetch(@json(route('search.suggest')) + '?q=' + encodeURIComponent(t),
                    { headers: { 'Accept': 'application/json' }, signal: this._ctrl.signal });
                const data = await res.json();
                this.clinics = data.clinics || [];
                this.services = data.services || [];
                this.doctors = data.doctors || [];
                this.open = this.hasResults;
            } catch (e) { /* aborted or network — ignore */ }
        },
    }));
});
</script>

@endsection
