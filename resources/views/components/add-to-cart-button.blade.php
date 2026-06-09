{{--
    "Add to cart" control. Drop next to a "book now" CTA. Pass `compact`
    for the icon-only variant (service cards) or omit it for the full
    labelled button (detail pages).

    Renders ONLY when the item's clinic has the cart feature visible
    (cart_status active AND storefront enabled). Logged-in customers get a
    plain POST form; guests get a button that opens the global OTP modal
    (public.partials.cart-otp-modal) because they must verify their phone
    before anything is added.

    Props: model, type ('service' | 'offer' | 'package'), clinic, compact, class.
--}}
@props(['model', 'type', 'clinic', 'compact' => false, 'class' => ''])

@if($clinic && $clinic->cartVisible())
    @php
        $inCart = auth('web')->check() && auth('web')->user()->hasInCart($model);
        $fullCls = 'inline-flex items-center justify-center gap-2 min-h-touch border border-sage-600 text-sage-700 hover:bg-sage-50 font-semibold px-5 py-3 rounded-lg transition-colors';
        $compactCls = 'inline-flex items-center justify-center w-9 h-9 rounded-full bg-white/95 text-gray-400 shadow-sm ring-1 ring-gray-100 transition-colors hover:text-sage-600 hover:bg-sage-50';
        $base = $compact ? $compactCls : $fullCls;
    @endphp

    @if($inCart)
        {{-- Already in the cart → shortcut to it instead of a no-op add. --}}
        <a href="{{ route('cart.index') }}"
           title="{{ __('site.cart_in_cart') }}" aria-label="{{ __('site.cart_in_cart') }}"
           class="{{ $compact ? 'inline-flex items-center justify-center w-9 h-9 rounded-full bg-sage-600 text-white shadow-sm ring-1 ring-sage-600' : 'inline-flex items-center justify-center gap-2 min-h-touch bg-sage-50 text-sage-700 font-semibold px-5 py-3 rounded-lg' }} {{ $class }}">
            <x-icon name="check-circle" class="w-5 h-5" />
            @unless($compact) <span>@lang('site.cart_in_cart')</span> @endunless
        </a>
    @elseif(auth('web')->check())
        <form method="POST" action="{{ route('cart.add') }}" class="inline {{ $class }}">
            @csrf
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="id" value="{{ $model->getKey() }}">
            <button type="submit" class="{{ $base }}"
                    title="{{ __('site.cart_add') }}" aria-label="{{ __('site.cart_add') }}">
                <x-icon name="shopping-bag" class="w-5 h-5" />
                @unless($compact) <span>@lang('site.cart_add')</span> @endunless
            </button>
        </form>
    @else
        {{-- Guest → open the OTP modal (it carries the type+id through). --}}
        <button type="button"
                data-cart-add data-cart-type="{{ $type }}" data-cart-id="{{ $model->getKey() }}"
                title="{{ __('site.cart_add') }}" aria-label="{{ __('site.cart_add') }}"
                class="{{ $base }} {{ $class }}">
            <x-icon name="shopping-bag" class="w-5 h-5" />
            @unless($compact) <span>@lang('site.cart_add')</span> @endunless
        </button>
    @endif
@endif
