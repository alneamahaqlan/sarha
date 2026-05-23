{{--
    Reusable Leaflet map (OpenStreetMap tiles, no API key).
    Params:
      $mapClinics   Collection|array of [id, name, slug, lat, lng]
      $mapId        (optional) DOM id, default 'saerha-map'
      $showAreaSearch (optional, bool) render a "Search this area" button (search page only)
--}}
@php
    $mapId = $mapId ?? 'saerha-map';
    $showAreaSearch = $showAreaSearch ?? false;
    $points = collect($mapClinics ?? [])->filter(fn ($c) => isset($c['lat'], $c['lng']))->values();
@endphp

@if($points->isNotEmpty())
    @once
        @push('head')
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
                  integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                    integrity="sha256-20nQCchB9co0qIjJ8sR1XzGiUb6QytaJZQ7eMtTk1gA=" crossorigin=""></script>
        @endpush
    @endonce

    <div class="relative">
        <div id="{{ $mapId }}" class="w-full h-80 md:h-96 rounded-2xl overflow-hidden border border-gray-200 z-0"></div>
        @if($showAreaSearch)
            <button type="button" id="{{ $mapId }}-area-btn"
                    class="absolute top-3 end-3 z-[400] bg-white text-teal-700 text-sm font-semibold px-4 py-2 rounded-lg shadow-md hover:bg-teal-50 transition-colors">
                @lang('site.map_search_this_area')
            </button>
        @endif
    </div>

    <script>
    (function () {
        var data = @json($points);
        var el = document.getElementById(@json($mapId));
        if (!el || !window.L || !data.length) return;

        var map = L.map(el, { scrollWheelZoom: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 18
        }).addTo(map);

        var viewLabel = @json(__('site.map_view_complex'));
        var clinicUrlTpl = @json(route('clinic.show', ['slug' => '__SLUG__']));
        var bounds = [];
        data.forEach(function (c) {
            if (c.lat == null || c.lng == null) return;
            var marker = L.marker([c.lat, c.lng]).addTo(map);
            var href = clinicUrlTpl.replace('__SLUG__', encodeURIComponent(c.slug));
            marker.bindPopup(
                '<strong>' + (c.name || '') + '</strong><br>' +
                '<a href="' + href + '" class="text-teal-600">' + viewLabel + '</a>'
            );
            bounds.push([c.lat, c.lng]);
        });

        if (bounds.length === 1) {
            map.setView(bounds[0], 13);
        } else if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [30, 30], maxZoom: 13 });
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
