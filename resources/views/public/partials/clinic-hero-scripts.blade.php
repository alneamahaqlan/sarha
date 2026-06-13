{{--
    Alpine definitions the clinic hero depends on: the specialty filter store
    and the Instagram-style story viewer. Extracted from clinic.blade.php so the
    same hero can be reused as a landing-page header section. Pushed to the
    'scripts' stack; safe to include once per page in any context.
--}}
@push('scripts')
<script>
    // Specialty filter store — clicking a category chip in the hero narrows
    // every content section (offers, services, sub-clinics, doctors, packages)
    // to items tagged with that specialty. `active` is the selected category
    // id (or null = show all). Each filterable card calls show(itsCatIds).
    document.addEventListener('alpine:init', () => {
        if (Alpine.store('spec')) return; // already defined on this page
        Alpine.store('spec', {
            active: null,
            names: {},                          // id -> display name (for the banner)
            toggle(id) { this.active = this.active === id ? null : id; },
            clear() { this.active = null; },
            isActive(id) { return this.active === id; },
            // A card shows when no filter is active, or its category list
            // includes the active specialty. Cards with no specialty fall out
            // while a filter is active (they aren't tied to this specialty).
            show(cats) { return !this.active || (cats || []).includes(this.active); },
            get activeName() { return this.active ? (this.names[this.active] || '') : ''; },
        });
    });

    // Instagram-style story viewer: full-screen overlay with segmented
    // progress bars, auto-advance, and tap-to-navigate.
    if (typeof window.storyViewer !== 'function') {
        window.storyViewer = function (items) {
            return {
                items: items || [],
                open: false,
                index: 0,
                progress: 0,
                duration: 5000,   // ms per story
                _timer: null,
                _seen: new Set(),   // story ids already counted this page load
                show(i = 0) {
                    if (!this.items.length) return;
                    this.index = i;
                    this.open = true;
                    document.body.style.overflow = 'hidden';
                    this.track();
                    this.play();
                },
                // Count a view once per story per page load (fire-and-forget).
                track() {
                    const item = this.items[this.index];
                    if (!item || this._seen.has(item.id)) return;
                    this._seen.add(item.id);
                    try {
                        const fd = new FormData();
                        fd.append('story', item.id);
                        navigator.sendBeacon(@json(route('track.story')), fd);
                    } catch (e) { /* tracking must never break the viewer */ }
                },
                play() {
                    this.progress = 0;
                    clearInterval(this._timer);
                    const step = 50;
                    this._timer = setInterval(() => {
                        this.progress += (step / this.duration) * 100;
                        if (this.progress >= 100) this.next();
                    }, step);
                },
                next() {
                    if (this.index < this.items.length - 1) { this.index++; this.track(); this.play(); }
                    else { this.close(); }
                },
                prev() {
                    if (this.index > 0) { this.index--; this.track(); }
                    this.play();
                },
                close() {
                    this.open = false;
                    clearInterval(this._timer);
                    document.body.style.overflow = '';
                },
            };
        };
    }
</script>
@endpush
