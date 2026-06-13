@extends('layouts.public')

@section('title', __('site.brand'))

@section('content')

{{-- ===================== HOMEPAGE CMS LOOP =====================
     Every section here is a row in homepage_sections, ordered by sort_order
     and filtered by HomepageSection::renderable() (active + within
     scheduling window). The admin reorders / toggles / edits from
     /app/admin/homepage-sections. Each row picks its partial by type:
     public.sections.{type}.blade.php.

     show_on_mobile / show_on_desktop are enforced via Tailwind hidden
     classes so the partial itself doesn't need to know about device gating.
================================================================ --}}

@foreach($sections as $entry)
    @php
        $section = $entry['section'];
        $data    = $entry['data'];
        $deviceGate = match (true) {
            ! $section->show_on_mobile && ! $section->show_on_desktop => 'hidden',
            ! $section->show_on_mobile                                 => 'hidden md:block',
            ! $section->show_on_desktop                                => 'md:hidden',
            default                                                    => '',
        };
    @endphp
    <div @class([$deviceGate => $deviceGate !== ''])>
        @includeIf('public.sections.' . $section->type, ['section' => $section, 'data' => $data, 'user' => $user])
    </div>
@endforeach

{{-- Complaint / platform-report call-to-action --}}
@include('public.partials.feedback-cta')

@push('scripts')
<script>
    // Scroll-reveal + animated counters — preserved verbatim from the
    // hardcoded version so the visual feel of the page is identical.
    (function () {
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var reveals = document.querySelectorAll('.reveal');
        if (reduce || !('IntersectionObserver' in window)) {
            reveals.forEach(function (el) { el.classList.add('in'); });
        } else {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
            reveals.forEach(function (el) { io.observe(el); });
        }

        var counters = document.querySelectorAll('[data-count]');
        function runCount(el) {
            var target = parseInt(el.getAttribute('data-count'), 10) || 0;
            if (reduce) { el.textContent = target.toLocaleString(); return; }
            var dur = 1400, start = null;
            function tick(ts) {
                if (start === null) start = ts;
                var p = Math.min((ts - start) / dur, 1);
                var eased = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.floor(eased * target).toLocaleString();
                if (p < 1) requestAnimationFrame(tick); else el.textContent = target.toLocaleString();
            }
            requestAnimationFrame(tick);
        }
        if ('IntersectionObserver' in window) {
            var cio = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (en.isIntersecting) { runCount(en.target); cio.unobserve(en.target); }
                });
            }, { threshold: 0.5 });
            counters.forEach(function (c) { cio.observe(c); });
        } else {
            counters.forEach(runCount);
        }
    })();

    function saerhaFindNearest() {
        var btn = document.getElementById('nearest-btn');
        var label = document.getElementById('nearest-btn-label');
        if (!navigator.geolocation) { window.location.href = @json(route('search', ['sort' => 'nearest'])); return; }
        var original = label.textContent;
        label.textContent = @json(__('site.locating'));
        btn.disabled = true;
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                var url = new URL(@json(route('search')));
                url.searchParams.set('sort', 'nearest');
                url.searchParams.set('lat', pos.coords.latitude);
                url.searchParams.set('lng', pos.coords.longitude);
                window.location.href = url.toString();
            },
            function () {
                label.textContent = original;
                btn.disabled = false;
                alert(@json(__('site.location_denied')));
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }
</script>
@endpush

@endsection
