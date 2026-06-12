{{-- First-party behavioral tracking for landing pages. Anonymous, no health
     context — only scroll depth, dwell, clicks, whatsapp/call, form submit. --}}
<script>
(function () {
    var root = document.querySelector('[data-lp-track-root]');
    if (!root) return;
    var pageId = parseInt(root.getAttribute('data-lp-id'), 10);
    if (!pageId) return;

    var qs = new URLSearchParams(window.location.search);
    var utm = {};
    ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].forEach(function (k) {
        if (qs.get(k)) utm[k] = qs.get(k);
    });

    var state = { visitId: null, maxScroll: 0, queue: [], started: Date.now(), finalized: false };

    function readCookie(name) {
        var m = document.cookie.match('(?:^|; )' + name + '=([^;]*)');
        return m ? decodeURIComponent(m[1]) : null;
    }

    // 1) Open the visit (fetch so we can read back the visit_id).
    fetch('/l/track/visit', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(Object.assign({
            page_id: pageId,
            visitor_id: readCookie('sarha_lv'),
            session_id: sessionStorage.getItem('sarha_lv_sess') || '',
            referrer: document.referrer || '',
            landing_url: window.location.href
        }, utm))
    }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
        if (d && d.visit_id) {
            state.visitId = d.visit_id;
            if (d.visitor_id) sessionStorage.setItem('sarha_lv_sess', d.visitor_id);
            flush();
        }
    }).catch(function () {});

    function enqueue(type, payload) {
        state.queue.push({ type: type, payload: payload || {} });
        if (state.queue.length >= 8) flush();
    }

    function flush() {
        if (!state.visitId || !state.queue.length) return;
        var batch = state.queue.splice(0, state.queue.length);
        var body = JSON.stringify({ visit_id: state.visitId, events: batch });
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/l/track/event', new Blob([body], { type: 'application/json' }));
        } else {
            fetch('/l/track/event', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: body, keepalive: true }).catch(function () {});
        }
    }

    // 2) Scroll depth milestones.
    var milestones = [25, 50, 75, 100], hit = {};
    window.addEventListener('scroll', function () {
        var h = document.documentElement;
        var depth = Math.min(100, Math.round((h.scrollTop + window.innerHeight) / h.scrollHeight * 100));
        if (depth > state.maxScroll) state.maxScroll = depth;
        milestones.forEach(function (m) {
            if (depth >= m && !hit[m]) { hit[m] = true; enqueue('scroll', { depth: m }); }
        });
    }, { passive: true });

    // 3) Dwell ticks (every 15s of an active tab).
    setInterval(function () {
        if (document.visibilityState === 'visible') enqueue('dwell', { seconds: 15 });
    }, 15000);

    // 4) Explicit interaction tracking via data-lp-track attributes.
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-lp-track]');
        if (!el) return;
        var type = el.getAttribute('data-lp-track');
        var button = el.getAttribute('data-lp-button') || undefined;
        enqueue(type, button ? { button: button } : {});
        if (type !== 'form_submit') flush();
    });

    // 5) Finalize on leave.
    function finalize() {
        if (state.finalized) return;
        state.finalized = true;
        flush();
        if (!state.visitId) return;
        var body = JSON.stringify({
            visit_id: state.visitId,
            duration_seconds: Math.round((Date.now() - state.started) / 1000),
            scroll_depth: state.maxScroll
        });
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/l/track/leave', new Blob([body], { type: 'application/json' }));
        }
    }
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') finalize(); });
    window.addEventListener('pagehide', finalize);
})();
</script>
