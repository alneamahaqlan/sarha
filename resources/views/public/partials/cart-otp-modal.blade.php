{{--
    Global add-to-cart modal for GUESTS. Opened by any [data-cart-add] button
    (set by <x-add-to-cart-button> for logged-out visitors). Two states:
      1. collect — name + phone → POST cart.add (carries type+id)
      2. verify  — OTP code → POST cart.add.verify
    The server re-opens this modal in the verify state by flashing
    `cart_otp_required` (distinct from the booking flow's `otp_required`).
    Rendered once in the public layout, guests only.
--}}
@php $cartOtp = session('cart_otp_required'); @endphp
<div id="cart-otp-modal"
     class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/40 px-4"
     @if($cartOtp) data-otp-open="1" @endif
     role="dialog" aria-modal="true" aria-labelledby="cart-otp-title">
    <div class="w-full max-w-sm bg-white rounded-2xl shadow-2xl p-6 relative">
        <button type="button" data-cart-close
                class="absolute top-3 end-3 text-gray-400 hover:text-gray-600" aria-label="@lang('site.close')">
            <x-icon name="x" class="w-5 h-5" />
        </button>

        <h2 id="cart-otp-title" class="font-display text-lg font-bold text-charcoal mb-1">@lang('site.cart_add_title')</h2>
        <p class="text-sm text-slate-warm mb-4">@lang('site.cart_otp_intro')</p>

        {{-- Step 1: collect name + phone --}}
        <form method="POST" action="{{ route('cart.add') }}" class="space-y-3" data-cart-step="collect"
              @if($cartOtp) hidden @endif>
            @csrf
            <input type="hidden" name="type" data-cart-type-field>
            <input type="hidden" name="id" data-cart-id-field>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.full_name') <span class="text-red-500">*</span></label>
                <input type="text" name="customer_name" required maxlength="255" value="{{ old('customer_name') }}"
                       class="w-full border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-sage-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.phone_number') <span class="text-red-500">*</span></label>
                <input type="tel" name="customer_phone" required inputmode="numeric" pattern="05\d{8}" placeholder="05XXXXXXXX"
                       value="{{ old('customer_phone') }}" dir="ltr"
                       class="w-full border border-gray-200 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-sage-400">
            </div>
            <button type="submit" class="w-full bg-sage-600 text-white py-3 rounded-lg font-semibold hover:bg-sage-700 transition-colors">
                @lang('site.cart_add')
            </button>
        </form>

        {{-- Step 2: verify OTP (auto-shown when the server asks for it) --}}
        <form method="POST" action="{{ route('cart.add.verify') }}" class="space-y-3" data-cart-step="verify"
              @unless($cartOtp) hidden @endunless>
            @csrf
            <div class="bg-sage-50 border border-sage-200 text-sage-900 rounded-lg p-3 text-sm space-y-1">
                <p>@lang('site.otp_step_intro')</p>
                <p class="text-xs text-sage-700">{{ __('site.otp_sent_to', ['phone' => session('cart_otp_phone')]) }}</p>
                @if(session('cart_dev_code'))
                    <p class="text-xs font-mono bg-white inline-block px-2 py-1 rounded border border-sage-200">DEV: {{ session('cart_dev_code') }}</p>
                @endif
            </div>
            @error('code')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">@lang('site.otp_code_label') <span class="text-red-500">*</span></label>
                <input type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6"
                       autocomplete="one-time-code" required
                       class="w-full border border-gray-200 rounded-lg px-4 py-3 text-center tracking-[0.5em] text-lg focus:outline-none focus:ring-2 focus:ring-sage-400" dir="ltr">
            </div>
            <button type="submit" class="w-full bg-sage-600 text-white py-3 rounded-lg font-semibold hover:bg-sage-700 transition-colors">
                @lang('site.otp_verify_submit')
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('cart-otp-modal');
    if (!modal) return;
    function open() { modal.classList.remove('hidden'); modal.classList.add('flex'); }
    function close() { modal.classList.add('hidden'); modal.classList.remove('flex'); }

    document.addEventListener('click', function (e) {
        var add = e.target.closest('[data-cart-add]');
        if (add) {
            e.preventDefault();
            modal.querySelector('[data-cart-type-field]').value = add.dataset.cartType || '';
            modal.querySelector('[data-cart-id-field]').value = add.dataset.cartId || '';
            open();
            return;
        }
        if (e.target.closest('[data-cart-close]')) { close(); return; }
        if (e.target === modal) { close(); } // backdrop
    });

    // Server asked to continue at the OTP step → auto-open.
    if (modal.dataset.otpOpen === '1') { open(); }
})();
</script>
