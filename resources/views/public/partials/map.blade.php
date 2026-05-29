{{--
    Reusable Leaflet map (OpenStreetMap tiles — free, no API key).
    Each marker renders a self-contained "card" popup with the clinic's logo,
    name, short snippet from the DB, Google review rating + count, top
    categories, starting price, and direct links to details/directions.

    Params:
      $mapClinics      Collection|array of marker dicts (see SearchController::index for shape).
                       Minimum required keys: id, name, slug, lat, lng.
                       Optional: url, logo, city, snippet, rating, reviews_count,
                                 categories[], min_price, directions, featured, distance_km.
      $mapId           (optional) DOM id, default 'saerha-map'
      $showAreaSearch  (optional bool) render a "Search this area" button (search page only)
      $userLat,$userLng (optional) the visitor's coordinates — drives the "you are here" marker
      $tall            (optional bool) use the taller search-page layout
--}}
@php
    $mapId          = $mapId ?? 'saerha-map';
    $showAreaSearch = $showAreaSearch ?? false;
    $tall           = $tall ?? false;
    $userLat        = $userLat ?? null;
    $userLng        = $userLng ?? null;
    $points         = collect($mapClinics ?? [])->filter(fn ($c) => isset($c['lat'], $c['lng']))->values();
@endphp

@if($points->isNotEmpty())
    @once
        @push('head')
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
                  integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
            {{-- NOTE: The leaflet.js integrity hash in the original partial was
                 stale (sha256-20nQCchB9co0qIjJ8sR1XzGiUb6QytaJZQ7eMtTk1gA=) and
                 mismatched the file unpkg actually serves, so every browser
                 blocked the script and the map silently failed to render.
                 Updated to the live hash served from unpkg/cdnjs. --}}
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
            <style>
                /* Tight, card-style popups for marker results. */
                .saerha-popup .leaflet-popup-content-wrapper {
                    border-radius: 14px;
                    padding: 0;
                    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.18);
                }
                .saerha-popup .leaflet-popup-content {
                    margin: 0;
                    width: 270px !important;
                    font-family: inherit;
                }
                .saerha-popup .leaflet-popup-tip { box-shadow: 0 6px 12px rgba(15, 23, 42, 0.12); }
                .saerha-popup-card { padding: 12px 14px 14px; color: #1f2937; }
                .saerha-popup-card .sp-head { display: flex; gap: 10px; align-items: center; margin-bottom: 6px; }
                .saerha-popup-card .sp-logo {
                    width: 44px; height: 44px; border-radius: 10px; object-fit: cover;
                    background: #ecfdf5; flex-shrink: 0;
                }
                .saerha-popup-card .sp-logo-fallback {
                    width: 44px; height: 44px; border-radius: 10px; flex-shrink: 0;
                    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
                    display: flex; align-items: center; justify-content: center;
                    color: #047857; font-weight: 700;
                }
                .saerha-popup-card .sp-name {
                    font-weight: 700; font-size: 15px; line-height: 1.25;
                    color: #0f172a; text-decoration: none;
                }
                .saerha-popup-card .sp-name:hover { color: #047857; }
                .saerha-popup-card .sp-meta { display: flex; flex-wrap: wrap; gap: 6px 10px; font-size: 12px; color: #6b7280; margin-top: 2px; }
                .saerha-popup-card .sp-rating { display: inline-flex; align-items: center; gap: 3px; color: #b45309; font-weight: 600; }
                .saerha-popup-card .sp-rating .sp-stars { color: #f59e0b; }
                .saerha-popup-card .sp-snippet { font-size: 12.5px; color: #475569; margin: 6px 0 8px; line-height: 1.5; }
                .saerha-popup-card .sp-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 10px; }
                .saerha-popup-card .sp-chip {
                    font-size: 11px; padding: 2px 8px; border-radius: 999px;
                    background: #ecfdf5; color: #047857;
                }
                .saerha-popup-card .sp-chip.sp-featured { background: #fef3c7; color: #92400e; }
                .saerha-popup-card .sp-price { font-size: 12px; color: #047857; font-weight: 600; margin-bottom: 8px; }
                .saerha-popup-card .sp-actions { display: flex; gap: 6px; }
                .saerha-popup-card .sp-btn {
                    flex: 1; text-align: center; font-size: 12px; font-weight: 600;
                    padding: 7px 8px; border-radius: 8px; text-decoration: none; transition: background .15s;
                }
                .saerha-popup-card .sp-btn-primary { background: #047857; color: #fff; }
                .saerha-popup-card .sp-btn-primary:hover { background: #065f46; }
                .saerha-popup-card .sp-btn-secondary { background: #f1f5f9; color: #334155; }
                .saerha-popup-card .sp-btn-secondary:hover { background: #e2e8f0; }

                /* "You are here" dot. */
                .saerha-user-dot {
                    width: 18px; height: 18px; border-radius: 50%;
                    background: #2563eb; border: 3px solid #fff;
                    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.25);
                }

                /* Floating "Locate me" control. */
                .saerha-locate-btn {
                    background: #fff; color: #047857; font-weight: 600;
                    padding: 7px 12px; border-radius: 999px; font-size: 13px;
                    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
                    display: inline-flex; align-items: center; gap: 6px;
                    transition: background .15s;
                }
                .saerha-locate-btn:hover { background: #ecfdf5; }
                .saerha-locate-btn:disabled { opacity: .6; cursor: default; }
            </style>
        @endpush
    @endonce

    {{-- min-w-0 on the relative wrapper guarantees Leaflet's tile layer
         (which can be 2500+ pixels wide internally) is fully contained
         even when this partial sits inside a flex/grid track that
         doesn't already constrain its children. Without it, the body
         picks up the leaked horizontal scroll on small viewports. --}}
    <div class="relative min-w-0 max-w-full">
        <div id="{{ $mapId }}"
             class="w-full {{ $tall ? 'h-96 md:h-[32rem]' : 'h-80 md:h-96' }} rounded-2xl overflow-hidden border border-gray-200 z-0"></div>

        {{-- Top-right action stack — "Locate me" + optional "Search this area". --}}
        <div class="absolute top-3 end-3 z-[400] flex flex-col gap-2 items-end">
            <button type="button" id="{{ $mapId }}-locate-btn"
                    class="saerha-locate-btn"
                    title="@lang('site.map_locate_me')">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                    <path fill-rule="evenodd"
                          d="M10 2a1 1 0 011 1v1.07a6.002 6.002 0 014.93 4.93H17a1 1 0 110 2h-1.07a6.002 6.002 0 01-4.93 4.93V17a1 1 0 11-2 0v-1.07A6.002 6.002 0 014.07 11H3a1 1 0 110-2h1.07A6.002 6.002 0 019 4.07V3a1 1 0 011-1zm0 4a4 4 0 100 8 4 4 0 000-8zm0 2a2 2 0 110 4 2 2 0 010-4z"
                          clip-rule="evenodd" />
                </svg>
                <span>@lang('site.map_locate_me')</span>
            </button>
            @if($showAreaSearch)
                <button type="button" id="{{ $mapId }}-area-btn"
                        class="bg-white text-sage-700 text-sm font-semibold px-4 py-2 rounded-lg shadow-md hover:bg-sage-50 transition-colors">
                    @lang('site.map_search_this_area')
                </button>
            @endif
        </div>
    </div>

    <script>
    (function () {
        var data = @json($points);
        var el = document.getElementById(@json($mapId));
        if (!el || !window.L || !data.length) return;

        var userLat = @json($userLat);
        var userLng = @json($userLng);

        var map = L.map(el, { scrollWheelZoom: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 18
        }).addTo(map);

        // i18n strings (Blade-resolved so the JS works in both Arabic and English).
        var T = {
            view:        @json(__('site.map_view_complex')),
            directions:  @json(__('site.action_directions')),
            from:        @json(__('site.starting_from', ['amount' => '__AMOUNT__'])),
            reviewsOne:  @json(__('site.map_reviews_one')),
            reviewsMany: @json(__('site.map_reviews_many', ['count' => '__N__'])),
            distance:    @json(__('site.map_distance_km', ['km' => '__KM__'])),
            featured:    @json(__('site.featured')),
            youHere:     @json(__('site.map_you_are_here')),
            noLocation:  @json(__('site.map_location_unavailable')),
        };

        function escapeHtml(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function formatAmount(n) {
            try { return new Intl.NumberFormat(document.documentElement.lang || 'ar').format(n); }
            catch (e) { return String(n); }
        }

        function buildPopupHtml(c) {
            var logoHtml = c.logo
                ? '<img class="sp-logo" src="' + escapeHtml(c.logo) + '" alt="' + escapeHtml(c.name) + '" loading="lazy">'
                : '<div class="sp-logo-fallback">' + escapeHtml((c.name || '?').slice(0, 1).toUpperCase()) + '</div>';

            var ratingHtml = '';
            if (c.rating) {
                var reviewsLabel = (c.reviews_count === 1)
                    ? T.reviewsOne
                    : T.reviewsMany.replace('__N__', c.reviews_count);
                ratingHtml = '<span class="sp-rating">'
                    + '<span class="sp-stars">★</span> ' + c.rating
                    + (c.reviews_count ? ' <span style="color:#94a3b8;font-weight:400">(' + escapeHtml(reviewsLabel) + ')</span>' : '')
                    + '</span>';
            }

            var distanceHtml = (c.distance_km != null)
                ? '<span>' + escapeHtml(T.distance.replace('__KM__', c.distance_km)) + '</span>'
                : '';

            var cityHtml = c.city ? '<span>' + escapeHtml(c.city) + '</span>' : '';

            var chipsHtml = '';
            if (c.featured) {
                chipsHtml += '<span class="sp-chip sp-featured">★ ' + escapeHtml(T.featured) + '</span>';
            }
            (c.categories || []).forEach(function (cat) {
                chipsHtml += '<span class="sp-chip">' + escapeHtml(cat) + '</span>';
            });

            var priceHtml = (c.min_price != null)
                ? '<div class="sp-price">' + escapeHtml(T.from.replace('__AMOUNT__', formatAmount(c.min_price))) + '</div>'
                : '';

            var snippetHtml = c.snippet ? '<p class="sp-snippet">' + escapeHtml(c.snippet) + '</p>' : '';

            var directionsHtml = c.directions
                ? '<a class="sp-btn sp-btn-secondary" href="' + escapeHtml(c.directions) + '" target="_blank" rel="noopener">' + escapeHtml(T.directions) + '</a>'
                : '';

            return ''
                + '<div class="saerha-popup-card">'
                +   '<div class="sp-head">'
                +     logoHtml
                +     '<div style="min-width:0;flex:1">'
                +       '<a class="sp-name" href="' + escapeHtml(c.url || '#') + '">' + escapeHtml(c.name) + '</a>'
                +       '<div class="sp-meta">' + ratingHtml + cityHtml + distanceHtml + '</div>'
                +     '</div>'
                +   '</div>'
                +   snippetHtml
                +   (chipsHtml ? '<div class="sp-chips">' + chipsHtml + '</div>' : '')
                +   priceHtml
                +   '<div class="sp-actions">'
                +     '<a class="sp-btn sp-btn-primary" href="' + escapeHtml(c.url || '#') + '">' + escapeHtml(T.view) + '</a>'
                +     directionsHtml
                +   '</div>'
                + '</div>';
        }

        // Render result markers + collect bounds.
        var bounds = [];
        data.forEach(function (c) {
            if (c.lat == null || c.lng == null) return;
            var marker = L.marker([c.lat, c.lng]).addTo(map);
            marker.bindPopup(buildPopupHtml(c), { className: 'saerha-popup', maxWidth: 280, minWidth: 270 });
            bounds.push([c.lat, c.lng]);
        });

        // Optional "you are here" marker.
        var userMarker = null;
        function placeUserMarker(lat, lng, fly) {
            if (userMarker) { map.removeLayer(userMarker); }
            userMarker = L.marker([lat, lng], {
                icon: L.divIcon({ className: '', html: '<div class="saerha-user-dot"></div>', iconSize: [18, 18], iconAnchor: [9, 9] }),
                zIndexOffset: 1000,
            }).addTo(map);
            userMarker.bindPopup('<strong>' + T.youHere + '</strong>');
            if (fly) { map.flyTo([lat, lng], Math.max(map.getZoom() || 12, 13)); }
        }

        if (userLat != null && userLng != null) {
            placeUserMarker(userLat, userLng, false);
            bounds.push([userLat, userLng]);
        }

        if (bounds.length === 1) {
            map.setView(bounds[0], 13);
        } else if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [40, 40], maxZoom: 13 });
        }

        // Recompute tile sizing after layout settles and when the map first
        // scrolls into view — fixes grey/blank tiles for below-the-fold maps.
        setTimeout(function () { map.invalidateSize(); }, 250);
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) { if (e.isIntersecting) { map.invalidateSize(); } });
            });
            io.observe(el);
        }

        // "Locate me" — uses navigator.geolocation (free, browser-native). When the
        // map sits inside the search form, we also wire the coords into the hidden
        // lat/lng fields and re-submit with sort=nearest so server-side ranking and
        // the result list update in lockstep.
        var locateBtn = document.getElementById(@json($mapId . '-locate-btn'));
        if (locateBtn) {
            locateBtn.addEventListener('click', function () {
                if (!navigator.geolocation) {
                    alert(T.noLocation);
                    return;
                }
                locateBtn.disabled = true;
                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        var lat = pos.coords.latitude, lng = pos.coords.longitude;
                        placeUserMarker(lat, lng, true);

                        @if($showAreaSearch)
                        var form = document.querySelector('form[action="{{ route('search') }}"]');
                        if (form) {
                            var sortEl = form.querySelector('#search-sort');
                            var latEl  = form.querySelector('#search-lat');
                            var lngEl  = form.querySelector('#search-lng');
                            if (sortEl) sortEl.value = 'nearest';
                            if (latEl)  latEl.value  = lat;
                            if (lngEl)  lngEl.value  = lng;
                            form.submit();
                            return;
                        }
                        @endif

                        locateBtn.disabled = false;
                    },
                    function () {
                        locateBtn.disabled = false;
                        alert(T.noLocation);
                    },
                    { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
                );
            });
        }

        @if($showAreaSearch)
        var areaBtn = document.getElementById(@json($mapId . '-area-btn'));
        if (areaBtn) {
            areaBtn.addEventListener('click', function () {
                var center = map.getCenter();
                var form = document.querySelector('form[action="{{ route('search') }}"]');
                if (!form) return;
                var sortEl = form.querySelector('#search-sort');
                var latEl = form.querySelector('#search-lat');
                var lngEl = form.querySelector('#search-lng');
                if (sortEl) sortEl.value = 'nearest';
                if (latEl) latEl.value = center.lat;
                if (lngEl) lngEl.value = center.lng;
                form.submit();
            });
        }
        @endif
    })();
    </script>
@endif
