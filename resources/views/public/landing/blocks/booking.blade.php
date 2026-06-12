{{-- Booking block — frictionless lead form. Posts straight to the complex
     (no OTP gate); the confirmation page then invites guests to register. --}}
@if($clinic)
    @php
        $u = auth('web')->user();
        $identity = $u ? ['name' => $u->name, 'phone' => $u->phone] : null;
        $heading = $cfg['heading'] ?? __('site.lp_book_now');
    @endphp
    <section id="lp-booking" class="bg-white py-14 scroll-mt-8" data-lp-form>
        <div class="max-w-xl mx-auto px-4">
            <h2 class="text-2xl font-bold text-gray-800 mb-2 text-center">{{ $heading }}</h2>
            <p class="text-center text-gray-500 mb-6">{{ $clinic->name }}</p>

            @if(session('success'))
                <div class="mb-4 rounded-lg bg-sage-50 border border-sage-200 text-sage-900 p-4 text-sm">{{ session('success') }}</div>
            @endif

            <form novalidate method="POST" action="{{ route('landing.book', $page->slug) }}" class="space-y-4 bg-gray-50 rounded-2xl p-6 ring-1 ring-gray-100"
                  data-lp-track-form>
                @csrf
                {{-- Attribution carried into the booking + visit. --}}
                @foreach(['utm_source','utm_medium','utm_campaign','utm_content','utm_term'] as $utm)
                    <input type="hidden" name="{{ $utm }}" value="{{ request()->query($utm, '') }}">
                @endforeach

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.full_name') <span class="text-red-500">*</span></label>
                    <input type="text" name="customer_name" required autocomplete="name"
                           value="{{ old('customer_name', $identity['name'] ?? '') }}"
                           class="w-full border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400">
                    @error('customer_name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.phone_number') <span class="text-red-500">*</span></label>
                    <input type="tel" name="customer_phone" required inputmode="tel" autocomplete="tel" pattern="05[0-9]{8}" placeholder="05XXXXXXXX" dir="ltr"
                           value="{{ old('customer_phone', $identity['phone'] ?? '') }}"
                           class="w-full border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400">
                    @error('customer_phone')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>

                @if($clinic->services->isNotEmpty())
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.requested_service')</label>
                        <x-form.select name="service_id">
                            <option value="">@lang('site.not_specified')</option>
                            @foreach($clinic->services as $svc)
                                <option value="{{ $svc->id }}" @selected(old('service_id') == $svc->id)>{{ $svc->name }}</option>
                            @endforeach
                        </x-form.select>
                        <p class="text-xs text-gray-500 mt-1.5">@lang('site.requested_service_other_hint')</p>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">@lang('site.lp_notes')</label>
                    <textarea name="notes" rows="2" class="w-full border border-gray-200 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sage-400">{{ old('notes') }}</textarea>
                </div>

                <button type="submit" data-lp-track="form_submit"
                        class="w-full bg-sage-600 text-white py-3.5 rounded-lg font-semibold hover:bg-sage-700 transition text-lg">
                    @lang('site.lp_send_booking')
                </button>
            </form>
        </div>
    </section>
@endif
