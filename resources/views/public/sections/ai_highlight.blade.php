{{-- AI highlight — plum section pitching the AI assistant. --}}
<section class="relative overflow-hidden bg-gradient-to-br from-plum-deep via-plum-primary to-plum-deep animate-gradient text-white">
    <div class="absolute inset-0 opacity-[0.08] pointer-events-none"
         style="background-image:radial-gradient(rgba(255,255,255,.8) 1px,transparent 1px);background-size:26px 26px"></div>
    <div class="absolute -top-24 -start-20 w-96 h-96 rounded-full bg-plum-medium/30 blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-28 -end-16 w-96 h-96 rounded-full bg-plum-soft/20 blur-3xl pointer-events-none"></div>

    <div class="relative max-w-6xl mx-auto px-4 py-20 grid md:grid-cols-2 gap-12 items-center">
        <div>
            <p class="reveal inline-flex items-center gap-2 text-sm font-semibold tracking-widest text-plum-soft uppercase mb-4">
                <span class="w-8 h-px bg-plum-soft"></span> @lang('site.ai_home_eyebrow')
            </p>
            <h2 class="reveal font-display text-3xl md:text-4xl font-bold leading-snug mb-4" style="--reveal-delay:80ms">
                {{ $section->title_ar && app()->getLocale() === 'ar' ? $section->title_ar : ($section->title_en && app()->getLocale() === 'en' ? $section->title_en : __('site.ai_home_title')) }}
            </h2>
            <p class="reveal text-plum-whisper/90 text-lg leading-relaxed mb-7" style="--reveal-delay:160ms">@lang('site.ai_home_desc')</p>
            <ul class="space-y-3 mb-8">
                @foreach(['ai_home_point1', 'ai_home_point2', 'ai_home_point3'] as $i => $point)
                    <li class="reveal flex items-center gap-3" style="--reveal-delay:{{ 220 + $i * 80 }}ms">
                        <span class="w-6 h-6 rounded-full bg-white/15 flex items-center justify-center shrink-0"><x-icon name="check-circle" class="w-4 h-4 text-gold-soft" /></span>
                        <span class="text-plum-whisper">@lang('site.' . $point)</span>
                    </li>
                @endforeach
            </ul>
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-ai-chat'))"
                    class="reveal inline-flex items-center gap-2 bg-white text-plum-deep px-7 py-3.5 rounded-xl font-bold hover:bg-plum-whisper active:scale-[0.98] transition-all shadow-xl shadow-plum-deep/30" style="--reveal-delay:480ms">
                <x-icon name="cpu" class="w-5 h-5" /> @lang('site.ai_home_cta')
            </button>
        </div>

        <div class="reveal reveal-zoom flex justify-center" style="--reveal-delay:160ms">
            <div class="relative w-64 h-64 rounded-full bg-white/5 ring-1 ring-white/10 flex items-center justify-center float">
                <div class="absolute inset-6 rounded-full border border-dashed border-white/15"></div>
                <svg width="170" height="170" viewBox="0 0 90 90" aria-hidden="true">
                    <path d="M 20 24 Q 20 20, 24 20 L 56 20 Q 62 20, 62 26 L 62 44 Q 62 58, 48 62 L 26 62 Q 20 62, 20 56 L 20 24 Z M 30 30 L 30 52 L 46 52 Q 52 52, 52 44 L 52 32 Q 52 30, 50 30 L 30 30 Z" fill="#FAF7F2"/>
                    <line class="ai-line ai-line-1" x1="62" y1="22" x2="76" y2="14" stroke="#E5D4A8" stroke-width="1.5" stroke-linecap="round"/>
                    <line class="ai-line ai-line-2" x1="62" y1="22" x2="80" y2="28" stroke="#E5D4A8" stroke-width="1.5" stroke-linecap="round"/>
                    <line class="ai-line ai-line-3" x1="76" y1="14" x2="80" y2="28" stroke="#C5B0D6" stroke-width="1.2" stroke-linecap="round" opacity="0.8"/>
                    <circle class="ai-dot ai-dot-1" cx="62" cy="22" r="4.5" fill="#C9A961"/>
                    <circle class="ai-dot ai-dot-2" cx="76" cy="14" r="3.5" fill="#E5D4A8"/>
                    <circle class="ai-dot ai-dot-3" cx="80" cy="28" r="3.5" fill="#E5D4A8"/>
                </svg>
            </div>
        </div>
    </div>
</section>
