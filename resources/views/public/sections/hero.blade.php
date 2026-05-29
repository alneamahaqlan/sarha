{{-- Hero — search + greeting. Cities come from $data['cities']. --}}
<section class="relative overflow-hidden bg-gradient-to-br from-sage-800 via-sage-700 to-sage-900 animate-gradient text-white">
    <div class="absolute inset-0 opacity-[0.10] pointer-events-none"
         style="background-image:linear-gradient(rgba(255,255,255,.7) 1px,transparent 1px),linear-gradient(to right,rgba(255,255,255,.7) 1px,transparent 1px);background-size:64px 64px"></div>
    {{-- Decorative blur orbs — hidden below sm because at 375px they
         extend 100+ pixels past the viewport edge despite the
         section's overflow-hidden, leaking a hairline document scroll
         on some Chromium configurations. They're purely cosmetic, so
         the visual loss on small phones is negligible. --}}
    <div class="hidden sm:block absolute -top-28 -end-24 w-[28rem] h-[28rem] rounded-full bg-gold-primary/20 blur-3xl pointer-events-none"></div>
    <div class="hidden sm:block absolute -bottom-40 -start-28 w-[28rem] h-[28rem] rounded-full bg-sage-400/25 blur-3xl pointer-events-none"></div>
    <div class="absolute top-20 start-8 w-24 h-24 bg-gold-primary/10 ring-1 ring-gold-primary/20 blob float hidden md:block pointer-events-none"></div>
    <div class="absolute bottom-24 end-12 w-16 h-16 bg-white/5 ring-1 ring-white/10 blob float float-delay-2 hidden md:block pointer-events-none"></div>
    <svg class="absolute top-14 end-20 w-36 h-36 opacity-50 float float-delay hidden lg:block pointer-events-none" viewBox="0 0 90 90" aria-hidden="true">
        <line class="ai-line ai-line-1" x1="20" y1="30" x2="46" y2="14" stroke="#E5D4A8" stroke-width="1" stroke-linecap="round"/>
        <line class="ai-line ai-line-2" x1="20" y1="30" x2="40" y2="52" stroke="#E5D4A8" stroke-width="1" stroke-linecap="round"/>
        <line class="ai-line ai-line-3" x1="46" y1="14" x2="72" y2="34" stroke="#E5D4A8" stroke-width="1" stroke-linecap="round"/>
        <line class="ai-line ai-line-2" x1="40" y1="52" x2="72" y2="34" stroke="#E5D4A8" stroke-width="1" stroke-linecap="round"/>
        <circle class="ai-dot ai-dot-1" cx="20" cy="30" r="4" fill="#C9A961"/>
        <circle class="ai-dot ai-dot-2" cx="46" cy="14" r="3.5" fill="#E5D4A8"/>
        <circle class="ai-dot ai-dot-3" cx="72" cy="34" r="3.5" fill="#E5D4A8"/>
        <circle class="ai-dot ai-dot-2" cx="40" cy="52" r="3" fill="#C9A961"/>
    </svg>

    <div class="relative max-w-5xl mx-auto px-4 pt-20 pb-28 md:pt-24 md:pb-32 text-center">
        <div class="reveal inline-flex items-center gap-2 bg-white/10 ring-1 ring-white/20 backdrop-blur-sm rounded-full ps-2 pe-4 py-1.5 text-sm text-sage-50 mb-6">
            <span class="relative flex h-2.5 w-2.5">
                <span class="absolute inline-flex h-full w-full rounded-full bg-gold-primary opacity-75" style="animation:pulse-ring 1.8s cubic-bezier(0,0,0.2,1) infinite"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-gold-primary"></span>
            </span>
            @lang('site.hero_badge')
        </div>

        @if($user ?? null)
            <p class="reveal text-sage-100 text-base md:text-lg mb-3" style="--reveal-delay:80ms">{{ __('site.greeting_welcome', ['name' => $user->name ?: '']) }}</p>
        @endif

        <h1 class="reveal font-display text-4xl md:text-6xl font-bold leading-[1.1] mb-5" style="--reveal-delay:120ms">
            {{ $section->title_ar && app()->getLocale() === 'ar' ? $section->title_ar : ($section->title_en && app()->getLocale() === 'en' ? $section->title_en : __('site.hero_title')) }}
        </h1>
        <p class="reveal text-sage-200 text-lg md:text-xl mb-10 max-w-2xl mx-auto" style="--reveal-delay:200ms">@lang('site.hero_subtitle')</p>

        <form action="{{ route('search') }}" method="GET"
              class="reveal bg-white rounded-2xl p-3 shadow-2xl ring-1 ring-black/5 max-w-3xl mx-auto" style="--reveal-delay:280ms"
              x-data="{ focused: false }" :class="focused ? 'ring-4 ring-gold-primary/40' : ''">
            <div class="flex flex-col md:flex-row gap-2.5">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 start-3 flex items-center text-gray-400 pointer-events-none"><x-icon name="search" class="w-5 h-5" /></span>
                    <input type="text" name="q" placeholder="@lang('site.search_placeholder')"
                           @focus="focused = true" @blur="focused = false"
                           class="w-full border border-gray-200 rounded-xl ps-11 pe-4 py-3.5 text-gray-800 focus:outline-none focus:ring-2 focus:ring-sage-500">
                </div>
                <select name="city" class="border border-gray-200 rounded-xl px-4 py-3.5 text-gray-700 focus:outline-none focus:ring-2 focus:ring-sage-500 md:min-w-44">
                    <option value="">@lang('site.search_all_cities')</option>
                    @foreach(($data['cities'] ?? collect()) as $city)
                        <option value="{{ $city->id }}">{{ $city->display_name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-sage-600 text-white px-8 py-3.5 rounded-xl font-semibold hover:bg-sage-700 active:scale-[0.98] transition-all whitespace-nowrap shadow-lg shadow-sage-900/30">
                    @lang('site.search_button')
                </button>
            </div>
        </form>

        <div class="reveal mt-6 flex flex-col items-center gap-5" style="--reveal-delay:360ms">
            <button type="button" id="nearest-btn" onclick="saerhaFindNearest()"
                    class="inline-flex items-center gap-1.5 text-sage-100 hover:text-white text-sm font-medium transition-colors">
                <x-icon name="map-pin" class="w-4 h-4" />
                <span id="nearest-btn-label">@lang('site.use_my_location')</span>
            </button>
            <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-sage-100/90">
                <span class="inline-flex items-center gap-1.5"><x-icon name="check-circle" class="w-4 h-4 text-gold-soft" /> @lang('site.hero_chip_verified')</span>
                <span class="inline-flex items-center gap-1.5"><x-icon name="scale" class="w-4 h-4 text-gold-soft" /> @lang('site.hero_chip_prices')</span>
                <span class="inline-flex items-center gap-1.5"><x-icon name="cpu" class="w-4 h-4 text-gold-soft" /> @lang('site.hero_chip_ai')</span>
            </div>
        </div>
    </div>
</section>
