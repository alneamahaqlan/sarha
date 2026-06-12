{{-- Hero — search + greeting. Cities come from $data['cities'].
     Compact green banner: dramatic sage gradient, white text, tightened
     vertical rhythm for a chic, smaller footprint. --}}
<section class="relative overflow-hidden bg-gradient-to-br from-sage-800 via-sage-700 to-sage-900 animate-gradient text-white">
    <div class="absolute inset-0 opacity-[0.08] pointer-events-none"
         style="background-image:linear-gradient(rgba(255,255,255,.7) 1px,transparent 1px),linear-gradient(to right,rgba(255,255,255,.7) 1px,transparent 1px);background-size:56px 56px"></div>
    {{-- Decorative blur orbs — hidden below sm because at 375px they
         extend past the viewport edge despite overflow-hidden, leaking a
         hairline document scroll on some Chromium configs. Cosmetic only. --}}
    <div class="hidden sm:block absolute -top-24 -end-20 w-80 h-80 rounded-full bg-gold-primary/20 blur-3xl pointer-events-none"></div>
    <div class="hidden sm:block absolute -bottom-32 -start-24 w-80 h-80 rounded-full bg-sage-400/25 blur-3xl pointer-events-none"></div>
    <svg class="absolute top-10 end-16 w-28 h-28 opacity-50 float float-delay hidden lg:block pointer-events-none" viewBox="0 0 90 90" aria-hidden="true">
        <line class="ai-line ai-line-1" x1="20" y1="30" x2="46" y2="14" stroke="#E5D4A8" stroke-width="1" stroke-linecap="round"/>
        <line class="ai-line ai-line-2" x1="20" y1="30" x2="40" y2="52" stroke="#E5D4A8" stroke-width="1" stroke-linecap="round"/>
        <line class="ai-line ai-line-3" x1="46" y1="14" x2="72" y2="34" stroke="#E5D4A8" stroke-width="1" stroke-linecap="round"/>
        <line class="ai-line ai-line-2" x1="40" y1="52" x2="72" y2="34" stroke="#E5D4A8" stroke-width="1" stroke-linecap="round"/>
        <circle class="ai-dot ai-dot-1" cx="20" cy="30" r="4" fill="#C9A961"/>
        <circle class="ai-dot ai-dot-2" cx="46" cy="14" r="3.5" fill="#E5D4A8"/>
        <circle class="ai-dot ai-dot-3" cx="72" cy="34" r="3.5" fill="#E5D4A8"/>
        <circle class="ai-dot ai-dot-2" cx="40" cy="52" r="3" fill="#C9A961"/>
    </svg>

    <div class="relative max-w-4xl mx-auto px-4 pt-12 pb-20 md:pt-16 md:pb-24 text-center">
        <div class="reveal inline-flex items-center gap-2 bg-white/10 ring-1 ring-white/20 backdrop-blur-sm rounded-full ps-2 pe-4 py-1.5 text-xs md:text-sm text-sage-50 mb-5">
            <span class="relative flex h-2.5 w-2.5">
                <span class="absolute inline-flex h-full w-full rounded-full bg-gold-primary opacity-75" style="animation:pulse-ring 1.8s cubic-bezier(0,0,0.2,1) infinite"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-gold-primary"></span>
            </span>
            @lang('site.hero_badge')
        </div>

        @if($user ?? null)
            <p class="reveal text-sage-100 text-sm md:text-base mb-2" style="--reveal-delay:80ms">{{ __('site.greeting_welcome', ['name' => $user->name ?: '']) }}</p>
        @endif

        <h1 class="reveal font-display text-3xl sm:text-4xl md:text-5xl font-bold leading-[1.12] tracking-tight mb-4" style="--reveal-delay:120ms">
            {{ $section->title_ar && app()->getLocale() === 'ar' ? $section->title_ar : ($section->title_en && app()->getLocale() === 'en' ? $section->title_en : __('site.hero_title')) }}
        </h1>
        <p class="reveal text-sage-200 text-base md:text-lg mb-7 max-w-xl mx-auto" style="--reveal-delay:200ms">@lang('site.hero_subtitle')</p>

        <form action="{{ route('search') }}" method="GET"
              class="reveal bg-white rounded-2xl p-2.5 shadow-2xl ring-1 ring-black/5 max-w-2xl mx-auto" style="--reveal-delay:280ms"
              x-data="{ focused: false }" :class="focused ? 'ring-4 ring-gold-primary/40' : ''">
            <div class="flex flex-col md:flex-row gap-2">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 start-3 flex items-center text-gray-400 pointer-events-none"><x-icon name="search" class="w-5 h-5" /></span>
                    <input type="text" name="q" placeholder="@lang('site.search_placeholder')"
                           @focus="focused = true" @blur="focused = false"
                           class="w-full border border-gray-200 rounded-xl ps-11 pe-4 py-3 text-gray-800 focus:outline-none focus:ring-2 focus:ring-sage-500">
                </div>
                <x-form.select name="city" class="md:w-auto md:min-w-40">
                    <option value="">@lang('site.search_all_cities')</option>
                    @foreach(($data['cities'] ?? collect()) as $city)
                        <option value="{{ $city->id }}">{{ $city->display_name }}</option>
                    @endforeach
                </x-form.select>
                <button type="submit" class="bg-sage-600 text-white px-7 py-3 rounded-xl font-semibold hover:bg-sage-700 active:scale-[0.98] transition-all whitespace-nowrap shadow-lg shadow-sage-900/30">
                    @lang('site.search_button')
                </button>
            </div>
        </form>

        <div class="reveal mt-5 flex flex-col items-center gap-4" style="--reveal-delay:360ms">
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
