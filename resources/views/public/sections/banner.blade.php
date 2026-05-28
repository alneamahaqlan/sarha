{{-- Auto-rotating banner — slides come from $data['slides']. Supports JPG/PNG/GIF/WebP.
     Interval comes from section->banner_interval_seconds (fallback 5s).
     RTL-safe and degrades to a static first slide if JS is disabled. --}}
@php
    $slides = $data['slides'] ?? collect();
    $interval = max(2000, (int) ($section->banner_interval_seconds ?? data_get($section->config, 'interval', 5)) * 1000);
@endphp
@if($slides->isNotEmpty())
<section class="px-4 py-10">
    <div class="max-w-7xl mx-auto">
        @if($section->title_ar || $section->title_en)
            <h2 class="reveal font-display text-2xl md:text-3xl font-bold text-charcoal mb-4">
                {{ app()->getLocale() === 'ar' ? ($section->title_ar ?: $section->title_en) : ($section->title_en ?: $section->title_ar) }}
            </h2>
        @endif
        <div
            x-data='{
                index: 0,
                count: {{ $slides->count() }},
                timer: null,
                start() {
                    if (this.count <= 1) return;
                    this.timer = setInterval(() => { this.index = (this.index + 1) % this.count; }, {{ $interval }});
                },
                stop() { if (this.timer) clearInterval(this.timer); },
                go(i) { this.index = i; this.stop(); this.start(); },
            }'
            x-init="start()"
            @mouseenter="stop()"
            @mouseleave="start()"
            class="relative overflow-hidden rounded-2xl shadow-lg ring-1 ring-gray-100 bg-gray-50">
            <div class="relative aspect-[21/9] sm:aspect-[24/9]">
                @foreach($slides as $i => $slide)
                    <div
                        x-show="index === {{ $i }}"
                        x-transition:enter="transition-opacity duration-700"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition-opacity duration-500"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        @class(['absolute inset-0', 'opacity-100' => $i === 0])
                        @if($i !== 0) style="display:none" @endif>
                        @if($slide->link_url)
                            <a href="{{ $slide->link_url }}" class="block w-full h-full">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($slide->image) }}"
                                     alt="" class="w-full h-full object-cover" loading="{{ $i === 0 ? 'eager' : 'lazy' }}">
                            </a>
                        @else
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($slide->image) }}"
                                 alt="" class="w-full h-full object-cover" loading="{{ $i === 0 ? 'eager' : 'lazy' }}">
                        @endif
                    </div>
                @endforeach
            </div>

            @if($slides->count() > 1)
                {{-- Dots --}}
                <div class="absolute bottom-3 inset-x-0 flex justify-center gap-1.5">
                    @foreach($slides as $i => $_)
                        <button type="button"
                                @click="go({{ $i }})"
                                :class="index === {{ $i }} ? 'bg-white w-6' : 'bg-white/60 w-2 hover:bg-white/90'"
                                class="h-2 rounded-full transition-all"
                                aria-label="Slide {{ $i + 1 }}"></button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
@endif
