@php
    $altLocale = app()->getLocale() === 'ar' ? 'en' : 'ar';
@endphp
{{-- Public navigation — links are admin-managed (navigation_links, location=header).
     Auth + language controls stay built-in. Fed by LayoutComposer. --}}
<nav class="bg-white shadow-sm sticky top-0 z-50" x-data="{ open: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center gap-2">
                {{-- Mobile hamburger — sized to clear 44px so a thumb tap never misses. --}}
                <button type="button" @click="open = !open"
                        class="md:hidden inline-flex items-center justify-center min-h-touch min-w-touch -ms-2 text-gray-600 hover:text-sage-600"
                        :aria-expanded="open.toString()" aria-label="@lang('site.menu')">
                    <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="open" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    <x-logo :size="40" />
                </a>
            </div>

            <div class="hidden md:flex items-center gap-6">
                @foreach($headerLinks as $link)
                    <a href="{{ $link->resolved_url }}"
                       @if($link->open_new_tab) target="_blank" rel="noopener" @endif
                       class="inline-flex items-center min-h-touch text-gray-600 hover:text-sage-600 transition-colors">{{ $link->label }}</a>
                @endforeach
            </div>

            <div class="flex items-center gap-3">
                {{-- Language switcher — min-h-touch so it clears 44px on mobile thumbs. --}}
                <a href="{{ route('lang.switch', $altLocale) }}"
                   class="inline-flex items-center justify-center min-h-touch text-sm text-gray-600 hover:text-sage-600 transition-colors px-3 border border-gray-200 rounded-lg"
                   title="@lang('site.language')">
                    {{ $altLocale === 'en' ? 'English' : 'العربية' }}
                </a>

                @auth('web')
                    @php $cartCount = auth('web')->user()->cartCount(); @endphp
                    <a href="{{ route('cart.index') }}" title="@lang('site.cart_title')" aria-label="@lang('site.cart_title')"
                       class="relative inline-flex items-center justify-center min-h-touch w-10 text-gray-700 hover:text-sage-600 transition-colors">
                        <x-icon name="shopping-bag" class="w-6 h-6" />
                        @if($cartCount > 0)
                            <span class="absolute top-1 end-1 min-w-[18px] h-[18px] px-1 rounded-full bg-sage-600 text-white text-[10px] font-bold flex items-center justify-center">{{ $cartCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('account.show') }}"
                       class="inline-flex items-center gap-1.5 min-h-touch text-sm text-gray-700 hover:text-sage-600 transition-colors">
                        <span class="w-7 h-7 rounded-full bg-sage-100 text-sage-700 flex items-center justify-center text-xs font-bold">
                            {{ mb_substr(auth('web')->user()->name ?: 'م', 0, 1) }}
                        </span>
                        <span class="hidden sm:inline">{{ auth('web')->user()->name ?: __('site.account_title') }}</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center min-h-touch text-sm text-gray-500 hover:text-red-500">@lang('site.nav_logout')</button>
                    </form>
                @else
                    <a href="{{ route('login', ['redirect' => url()->current()]) }}" class="inline-flex items-center justify-center min-h-touch bg-sage-600 text-white px-4 rounded-lg text-sm hover:bg-sage-700 transition-colors">
                        @lang('site.nav_login')
                    </a>
                @endauth
            </div>
        </div>

        {{-- Mobile menu panel — each row gets min-h-touch so the whole strip
             is a comfortable thumb target. --}}
        <div x-show="open" x-cloak @click.outside="open = false"
             class="md:hidden border-t border-gray-100 py-2">
            @foreach($headerLinks as $link)
                <a href="{{ $link->resolved_url }}"
                   @if($link->open_new_tab) target="_blank" rel="noopener" @endif
                   class="flex items-center min-h-touch px-2 text-gray-700 hover:text-sage-600">{{ $link->label }}</a>
            @endforeach
            @auth('web')
                <a href="{{ route('cart.index') }}" class="flex items-center min-h-touch px-2 text-gray-700 hover:text-sage-600">@lang('site.cart_title')</a>
                <a href="{{ route('account.show') }}" class="flex items-center min-h-touch px-2 text-gray-700 hover:text-sage-600">@lang('site.account_profile')</a>
                <a href="{{ route('account.bookings') }}" class="flex items-center min-h-touch px-2 text-gray-700 hover:text-sage-600">@lang('site.account_my_bookings')</a>
                <a href="{{ route('account.favorites') }}" class="flex items-center min-h-touch px-2 text-gray-700 hover:text-sage-600">@lang('site.account_my_favorites')</a>
            @else
                <a href="{{ route('login', ['redirect' => url()->current()]) }}" class="flex items-center min-h-touch px-2 text-gray-700 hover:text-sage-600">@lang('site.nav_login')</a>
            @endauth
        </div>
    </div>
</nav>
