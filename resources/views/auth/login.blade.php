@extends('layouts.public')

@section('title', __('site.login_title'))

@section('content')
{{-- Two-step OTP login. The step (phone → code) is held in client state so a
     wrong code or any server error re-renders INLINE and never bounces the
     user back to phone entry. It's progressive enhancement: the forms keep
     native action/method, so with JS off they fall back to the classic
     flash-based flow the controller still serves for non-JSON requests. --}}
<div class="min-h-screen flex items-center justify-center px-4 py-12 bg-gray-50">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-sm p-8"
             x-data="otpLogin(@js([
                'urls' => ['send' => route('login.otp'), 'verify' => route('login.verify'), 'complete' => route('login.complete')],
                'csrf' => csrf_token(),
                'genericError' => __('site.otp_network_error'),
                'sentToTemplate' => __('site.otp_sent_to', ['phone' => '__PHONE__']),
                'devCodeTemplate' => __('site.dev_code', ['code' => '__CODE__']),
                'resendLabel' => __('site.otp_resend'),
                'resendInTemplate' => __('site.otp_resend_in', ['seconds' => '__S__']),
             ]))">

            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-sage-600 mb-1">@lang('site.brand')</h1>
                <p class="text-gray-500 text-sm">@lang('site.login_subtitle')</p>
            </div>

            {{-- Server-side errors (non-JS fallback) + client errors share this banner. --}}
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm mb-5">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif
            <div x-show="error" x-cloak x-transition
                 class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm mb-5">
                <p x-text="error"></p>
            </div>

            {{-- ── Step 1: phone (visible by default — survives even if JS fails) ── --}}
            <form method="POST" action="{{ route('login.otp') }}"
                  x-show="step === 'phone'" @submit.prevent="sendOtp" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">@lang('site.phone_number')</label>
                    <input type="tel" name="phone" x-model="phone" value="{{ old('phone') }}" required
                           placeholder="05XXXXXXXX"
                           inputmode="tel" autocomplete="tel" pattern="05[0-9]{8}"
                           class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400 text-center tracking-wider"
                           dir="ltr">
                </div>
                <button type="submit" :disabled="loading"
                        class="w-full bg-sage-600 text-white py-3 rounded-xl font-semibold hover:bg-sage-700 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                    <span x-show="!loading">@lang('site.send_otp')</span>
                    <span x-show="loading" x-cloak>…</span>
                </button>
            </form>

            {{-- ── Step 2: code (hidden until a code is sent) ── --}}
            <form method="POST" action="{{ route('login.verify') }}"
                  x-show="step === 'otp'" x-cloak @submit.prevent="verify" class="space-y-4">
                @csrf
                <input type="hidden" name="phone" :value="phone">
                <div class="text-center text-sm text-gray-600 mb-2">
                    <span x-text="sentToText"></span>
                    <template x-if="devCode">
                        <span class="block text-sage-600 font-bold mt-1" x-text="devCodeText"></span>
                    </template>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">@lang('site.otp_label')</label>
                    <input type="text" name="code" x-model="code" x-ref="codeInput" maxlength="6" required
                           placeholder="XXXXXX"
                           inputmode="numeric" pattern="\d{6}"
                           @input="code = code.replace(/\D/g, '')"
                           class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400 text-center tracking-[0.5em] text-xl"
                           dir="ltr" autocomplete="one-time-code">
                </div>
                <button type="submit" :disabled="loading || code.length !== 6"
                        class="w-full bg-sage-600 text-white py-3 rounded-xl font-semibold hover:bg-sage-700 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                    <span x-show="!loading">@lang('site.verify_and_enter')</span>
                    <span x-show="loading" x-cloak>…</span>
                </button>

                <div class="flex items-center justify-between text-sm mt-4">
                    <button type="button" @click="changePhone"
                            class="text-gray-500 hover:text-sage-600">@lang('site.change_phone')</button>
                    <button type="button" @click="resend" :disabled="cooldown > 0 || loading"
                            class="font-medium text-sage-600 hover:text-sage-700 disabled:text-gray-400 disabled:cursor-not-allowed"
                            x-text="resendText"></button>
                </div>
            </form>

            {{-- ── Step 3: name (new users only — mandatory before account is created) ── --}}
            <form method="POST" action="{{ route('login.complete') }}"
                  x-show="step === 'name'" x-cloak @submit.prevent="completeProfile" class="space-y-4">
                @csrf
                <div class="text-center text-sm text-gray-600 mb-2">@lang('site.name_step_intro')</div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">@lang('site.full_name')</label>
                    <input type="text" name="name" x-model="name" x-ref="nameInput" required minlength="2" maxlength="60"
                           placeholder="@lang('site.full_name')"
                           class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400">
                </div>
                <button type="submit" :disabled="loading || name.trim().length < 2"
                        class="w-full bg-sage-600 text-white py-3 rounded-xl font-semibold hover:bg-sage-700 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                    <span x-show="!loading">@lang('site.name_step_submit')</span>
                    <span x-show="loading" x-cloak>…</span>
                </button>
            </form>

            {{-- Create clinic account --}}
            <div x-show="step !== 'name'" class="mt-6 pt-6 border-t border-gray-100 text-center">
                <a href="{{ route('clinic.register') }}"
                   class="inline-flex items-center justify-center gap-2 w-full border border-sage-200 text-sage-700 py-3 rounded-xl font-semibold hover:bg-sage-50 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                    @lang('site.create_clinic_account')
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
{{-- Defined as a plain global BEFORE Livewire boots Alpine (end of body), so it
     never depends on alpine:init firing at a particular time. --}}
<script>
    function otpLogin(cfg) {
        return {
            step: 'phone',
            phone: @js(old('phone') ?? ''),
            code: '',
            name: '',
            devCode: null,
            error: '',
            loading: false,
            cooldown: 0,
            _timer: null,

            get sentToText() { return cfg.sentToTemplate.replace('__PHONE__', this.phone); },
            get devCodeText() { return cfg.devCodeTemplate.replace('__CODE__', this.devCode || ''); },
            get resendText() {
                return this.cooldown > 0
                    ? cfg.resendInTemplate.replace('__S__', this.cooldown)
                    : cfg.resendLabel;
            },

            async post(url, body) {
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': cfg.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(body),
                    });
                    let data = {};
                    try { data = await res.json(); } catch (e) {}
                    return { ok: res.ok, data: data };
                } catch (e) {
                    return { ok: false, data: { message: cfg.genericError } };
                }
            },

            errorOf(data) {
                if (data.message) return data.message;
                if (data.errors) {
                    const first = Object.keys(data.errors)[0];
                    if (first) return data.errors[first][0];
                }
                return cfg.genericError;
            },

            startCooldown(seconds) {
                this.cooldown = seconds || 0;
                clearInterval(this._timer);
                if (this.cooldown <= 0) return;
                this._timer = setInterval(() => {
                    if (--this.cooldown <= 0) clearInterval(this._timer);
                }, 1000);
            },

            async sendOtp() {
                if (this.loading) return;
                this.error = '';
                this.loading = true;
                const r = await this.post(cfg.urls.send, { phone: this.phone });
                this.loading = false;
                if (!r.ok || !r.data.ok) {
                    this.error = this.errorOf(r.data);
                    if (r.data.retry_after) this.startCooldown(r.data.retry_after);
                    return;
                }
                this.devCode = r.data.dev_code || null;
                this.code = '';
                this.step = 'otp';
                this.startCooldown(r.data.cooldown || 60);
                this.$nextTick(() => { if (this.$refs.codeInput) this.$refs.codeInput.focus(); });
            },

            async verify() {
                if (this.loading || this.code.length !== 6) return;
                this.error = '';
                this.loading = true;
                const r = await this.post(cfg.urls.verify, { phone: this.phone, code: this.code });
                this.loading = false;
                if (r.data.ok) {
                    // New user → must name themselves before the account exists.
                    if (r.data.needs_name) {
                        this.step = 'name';
                        this.$nextTick(() => { if (this.$refs.nameInput) this.$refs.nameInput.focus(); });
                        return;
                    }
                    window.location.href = r.data.redirect;
                    return;
                }
                this.error = this.errorOf(r.data);
                this.code = '';
                if (r.data.must_resend) this.startCooldown(0); // code burned → allow immediate resend, stay on step
                this.$nextTick(() => { if (this.$refs.codeInput) this.$refs.codeInput.focus(); });
            },

            async completeProfile() {
                if (this.loading || this.name.trim().length < 2) return;
                this.error = '';
                this.loading = true;
                const r = await this.post(cfg.urls.complete, { name: this.name.trim() });
                this.loading = false;
                if (r.data.ok) { window.location.href = r.data.redirect; return; }
                this.error = this.errorOf(r.data);
                // Session lost / never verified → restart from the phone step.
                if (r.data.must_restart) this.changePhone();
            },

            async resend() {
                if (this.cooldown > 0 || this.loading) return;
                await this.sendOtp();
            },

            changePhone() {
                this.step = 'phone';
                this.code = '';
                this.error = '';
                this.devCode = null;
                this.startCooldown(0);
            },
        };
    }
</script>
@endpush
@endsection
