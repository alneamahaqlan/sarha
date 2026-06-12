{{-- Personalised strip: live offers across the complexes the signed-in
     customer follows. Hidden entirely for guests / non-followers (the
     controller hands an empty collection). --}}
@php $followedOffers = $followedOffers ?? collect(); @endphp
@if($followedOffers->isNotEmpty())
{{-- offer-card relies on this Alpine factory for its countdown. The offers
     TAB partial defines an identical one, but that partial never loads on the
     homepage — so define it here too (each @once is independent, and a
     redefine is harmless if both ever render together). --}}
@once
    @push('scripts')
    <script>
        function offerCountdown(endsAtIso) {
            return {
                endsAt: new Date(endsAtIso).getTime(),
                label: '', urgent: false, _timer: null,
                update() {
                    const ms = this.endsAt - Date.now();
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
                        this.label = @json(__('site.offer_countdown_days')).replace(':d', Math.floor(hours / 24));
                        this.urgent = false;
                    }
                },
                start() { this.update(); this._timer = setInterval(() => this.update(), 60_000); },
            };
        }
    </script>
    @endpush
@endonce
<section class="py-12 px-4 bg-gradient-to-b from-sage-mist/40 to-white">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-end justify-between mb-8">
            <div>
                <p class="reveal text-sm font-semibold tracking-widest text-sage-600 uppercase mb-2">@lang('site.followed_eyebrow')</p>
                <h2 class="reveal font-display text-3xl font-bold text-charcoal" style="--reveal-delay:80ms">
                    @lang('site.followed_offers_title')
                </h2>
                <p class="reveal text-gray-500 text-sm mt-1" style="--reveal-delay:140ms">@lang('site.followed_offers_subtitle')</p>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
            @foreach($followedOffers as $i => $offer)
                <div class="reveal" style="--reveal-delay:{{ ($i % 4) * 90 }}ms">
                    @include('public.partials.offer-card', ['offer' => $offer, 'clinic' => $offer->clinic, 'large' => false])
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
