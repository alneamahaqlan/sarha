{{--
    Offers tab — standalone Offer entities (replaced the legacy
    is_featured_offer flag on services). Two visual tiers:
      • Featured offers (1–3) get a wider card at the top
      • Remaining active offers fill a 2-column grid below
    Each card carries its discount badge, prices, a countdown, and a
    type-appropriate CTA: "احجز هذه الخدمة" for service-linked offers,
    "تواصل للاستفسار" for general promos.
    Expects: $clinic, $activeOffers (Collection of Offer)
--}}
@php
    $activeOffers = $activeOffers ?? collect();
    $featured = $activeOffers->where('is_featured', true)->values();
    $regular  = $activeOffers->where('is_featured', false)->values();
@endphp

@once
    @push('scripts')
    <script>
        // Alpine factory used by every offer-card on the page. Ticks once
        // per minute (offer windows are measured in hours/days, not
        // seconds — so 60s saves a bunch of setInterval churn).
        function offerCountdown(endsAtIso) {
            return {
                endsAt: new Date(endsAtIso).getTime(),
                label: '',
                urgent: false,
                _timer: null,
                update() {
                    const now = Date.now();
                    const ms  = this.endsAt - now;
                    if (ms <= 0) {
                        this.label = @json(__('site.offer_countdown_expired'));
                        this.urgent = true;
                        if (this._timer) { clearInterval(this._timer); }
                        return;
                    }
                    const hours = Math.floor(ms / (1000 * 60 * 60));
                    if (hours < 1) {
                        this.label = @json(__('site.offer_countdown_ending'));
                        this.urgent = true;
                    } else if (hours < 24) {
                        this.label = @json(__('site.offer_countdown_hours')).replace(':h', hours);
                        this.urgent = true;
                    } else {
                        const days = Math.floor(hours / 24);
                        this.label = @json(__('site.offer_countdown_days')).replace(':d', days);
                        this.urgent = false;
                    }
                },
                start() {
                    this.update();
                    this._timer = setInterval(() => this.update(), 60_000);
                },
            };
        }
    </script>
    @endpush
@endonce

@if($activeOffers->isEmpty())
    <div class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
        @lang('site.no_offers_yet')
    </div>
@else
    @if($featured->isNotEmpty())
        <div class="space-y-4">
            <h2 class="text-lg font-bold text-gray-800">@lang('site.featured_offers_title')</h2>
            <div class="grid grid-cols-1 lg:grid-cols-{{ min($featured->count(), 3) }} gap-4">
                @foreach($featured as $offer)
                    @include('public.partials.offer-card', ['offer' => $offer, 'clinic' => $clinic, 'large' => true])
                @endforeach
            </div>
        </div>
    @endif

    @if($regular->isNotEmpty())
        <div class="space-y-4">
            @if($featured->isNotEmpty())
                <h2 class="text-lg font-bold text-gray-800 mt-6">@lang('site.more_offers_title')</h2>
            @endif
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($regular as $offer)
                    @include('public.partials.offer-card', ['offer' => $offer, 'clinic' => $clinic, 'large' => false])
                @endforeach
            </div>
        </div>
    @endif
@endif
