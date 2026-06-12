{{-- Countdown block — drives urgency toward an offer's end date. --}}
@php $target = $cfg['target'] ?? null; @endphp
@if($target)
    <section class="bg-sage-800 text-white py-10" x-data="lpCountdown('{{ \Illuminate\Support\Carbon::parse($target)->toIso8601String() }}')" x-cloak>
        <div class="max-w-3xl mx-auto px-4 text-center">
            @if(!empty($cfg['heading']))
                <h2 class="text-xl sm:text-2xl font-bold mb-5">{{ $cfg['heading'] }}</h2>
            @endif
            <template x-if="!ended">
                <div class="flex items-center justify-center gap-3 sm:gap-5" dir="ltr">
                    <template x-for="part in parts" :key="part.label">
                        <div class="flex flex-col items-center bg-white/10 rounded-xl px-4 py-3 min-w-[64px]">
                            <span class="text-2xl sm:text-3xl font-bold tabular-nums" x-text="String(part.value).padStart(2,'0')"></span>
                            <span class="text-xs text-white/70" x-text="part.label"></span>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="ended">
                <p class="text-lg text-white/80">@lang('site.lp_offer_ended')</p>
            </template>
        </div>
    </section>

    @once
        @push('scripts')
        <script>
            function lpCountdown(iso) {
                return {
                    target: new Date(iso).getTime(),
                    ended: false,
                    parts: [],
                    init() { this.tick(); setInterval(() => this.tick(), 1000); },
                    tick() {
                        const diff = this.target - Date.now();
                        if (diff <= 0) { this.ended = true; return; }
                        const d = Math.floor(diff / 86400000);
                        const h = Math.floor((diff % 86400000) / 3600000);
                        const m = Math.floor((diff % 3600000) / 60000);
                        const s = Math.floor((diff % 60000) / 1000);
                        this.parts = [
                            { label: @json(__('site.lp_days')),    value: d },
                            { label: @json(__('site.lp_hours')),   value: h },
                            { label: @json(__('site.lp_minutes')), value: m },
                            { label: @json(__('site.lp_seconds')), value: s },
                        ];
                    },
                };
            }
        </script>
        @endpush
    @endonce
@endif
