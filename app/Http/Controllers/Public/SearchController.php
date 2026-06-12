<?php

namespace App\Http\Controllers\Public;

use App\Enums\ImpressionSource;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Clinic;
use App\Services\ImpressionTrackerService;
use App\Services\UserActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public const SORT_OPTIONS = ['featured', 'top_rated', 'cheapest', 'most_booked', 'nearest'];

    /** Filter inputs remembered across visits so "Back" returns to the same results (#8). */
    private const FILTER_KEYS = ['q', 'city', 'category', 'district', 'min_rating', 'price_max', 'featured', 'open_now', 'sort', 'lat', 'lng'];

    public function index(Request $request)
    {
        // Persist/restore filters in the session: a bare /search visit (e.g. via
        // browser Back from a clinic page) is redirected to the last filter set.
        // ?clear=1 wipes the memory (used by the "clear filters" link).
        if ($request->boolean('clear')) {
            $request->session()->forget('search_filters');
        } elseif (! $request->hasAny(self::FILTER_KEYS)) {
            $saved = $request->session()->get('search_filters', []);
            if (! empty($saved)) {
                return redirect()->route('search', $saved);
            }
        }

        if ($request->hasAny(self::FILTER_KEYS)) {
            $active = collect($request->only(self::FILTER_KEYS))
                ->filter(fn ($v) => $v !== null && $v !== '')
                ->all();
            $request->session()->put('search_filters', $active);
        }

        // withPackageFeatures() joins subscription_packages once so every
        // sort branch + the map-markers payload reads pkg_* on the row
        // instead of re-hitting FeatureGate per clinic.
        $query = Clinic::publiclyVisible()
            ->withPackageFeatures()
            ->with(['city', 'categories'])
            ->withAvg('googleReviews', 'rating')
            ->withCount('bookings')
            ->withMin(['services as min_price' => fn($q) => $q->where('is_active', true)->where('approval_status', 'approved')->whereNotNull('price')], 'price');

        if ($request->filled('city')) {
            $query->where('city_id', $request->city);
        }

        if ($request->filled('category')) {
            $query->whereHas('categories', fn($q) => $q->where('categories.id', $request->category));
        }

        if ($request->filled('q')) {
            $like = '%' . $request->q . '%';
            // Match the complex itself, its services (procedures), its doctors,
            // and its specialties — so "تبييض أسنان" or a doctor's name both work.
            $query->where(fn($q) => $q
                ->where('name', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('address', 'like', $like)
                ->orWhereHas('services', fn($s) => $s->where('is_active', true)->where('approval_status', 'approved')->notCatchall()->where('name', 'like', $like))
                ->orWhereHas('doctors', fn($d) => $d->where('name', 'like', $like))
                ->orWhereHas('categories', fn($c) => $c
                    ->where('categories.name', 'like', $like)
                    ->orWhere('categories.name_en', 'like', $like))
            );
        }

        // "Featured" filter — checks the package flag OR a manual admin
        // boost. Either source qualifies a clinic for the featured ribbon.
        if ($request->filled('featured')) {
            $query->where(function ($q) {
                $q->where('clinics.is_featured', true)
                  ->orWhereRaw('COALESCE(pkg.featured_in_search, 0) = 1');
            });
        }

        // "Open now" — clinics whose today's working hours include the current time.
        if ($request->boolean('open_now')) {
            $now = now();
            $query->whereHas('workingHours', fn($w) => $w
                ->where('day_of_week', $now->dayOfWeek)
                ->where('is_open', true)
                ->whereNotNull('opens_at')
                ->whereNotNull('closes_at')
                ->whereTime('opens_at', '<=', $now->format('H:i:s'))
                ->whereTime('closes_at', '>=', $now->format('H:i:s')));
        }

        if ($request->filled('district')) {
            $query->where('district', $request->district);
        }

        // Snapshot the filtered (pre-sort) query for the map markers so they reflect
        // the active filters (#62). NOTE: cloned BEFORE the aggregate HAVING filters
        // (rating/price) because the map query selects only id/coords, which would
        // break a HAVING that references the avg/min subquery aliases.
        $mapQuery = clone $query;

        // Aggregate filters (reference withAvg/withMin aliases via HAVING).
        if (is_numeric($request->input('min_rating'))) {
            $query->having('google_reviews_avg_rating', '>=', (float) $request->min_rating);
        }
        if (is_numeric($request->input('price_max'))) {
            $query->having('min_price', '<=', (float) $request->price_max);
        }

        $sort = in_array($request->input('sort'), self::SORT_OPTIONS, true)
            ? $request->input('sort')
            : 'featured';

        // 'nearest' only applies when valid coordinates are supplied; otherwise fall back.
        $lat = is_numeric($request->input('lat')) ? (float) $request->input('lat') : null;
        $lng = is_numeric($request->input('lng')) ? (float) $request->input('lng') : null;
        if ($sort === 'nearest' && ($lat === null || $lng === null)) {
            $sort = 'featured';
        }

        match ($sort) {
            'top_rated' => $query
                ->withAvg('googleReviews', 'rating')
                ->orderByDesc('pkg_featured_in_search')
                ->orderByDesc('clinics.is_featured')
                ->orderByDesc('google_reviews_avg_rating'),
            'cheapest' => $query
                ->withMin(['services as min_price' => fn($q) => $q->where('is_active', true)->where('approval_status', 'approved')->whereNotNull('price')], 'price')
                ->orderByDesc('pkg_featured_in_search')
                ->orderByDesc('clinics.is_featured')
                ->orderBy('min_price'),
            'most_booked' => $query
                ->withCount('bookings')
                ->orderByDesc('pkg_featured_in_search')
                ->orderByDesc('clinics.is_featured')
                ->orderByDesc('bookings_count'),
            'nearest' => $query
                // Haversine distance (km); clinics with null coords sort last.
                // NOTE: must NOT prefix with "*," — the base query already selects
                // all clinic columns, and withAvg/withCount/withMin have appended
                // scalar-subquery columns. A second "*" would produce invalid SQL
                // ("clinics.*, (sub), *, CASE …"), so we only ADD the distance col.
                ->selectRaw(
                    'CASE WHEN latitude IS NULL OR longitude IS NULL THEN NULL ELSE '
                    . '(6371 * acos(LEAST(1, GREATEST(-1, '
                    . 'cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) '
                    . '+ sin(radians(?)) * sin(radians(latitude)))))) END AS distance_km',
                    [$lat, $lng, $lat]
                )
                ->orderByRaw('distance_km IS NULL')
                ->orderBy('distance_km'),
            default => $query->rankedForListing(),
        };

        // Geocoded coords for the map — reflects the *full* filtered set (not just
        // the current page) so markers stay in sync with active filters (#62). The
        // payload is intentionally rich enough to render a self-contained "card" inside
        // each marker popup (name, snippet, Google rating + count, top categories,
        // starting price, directions URL) without any extra round-trip per marker.
        $mapClinics = $mapQuery
            ->with(['city:id,name,name_en', 'categories:id,name,name_en'])
            ->withAvg('googleReviews', 'rating')
            ->withCount('googleReviews')
            ->withMin(['services as min_price' => fn($q) => $q->where('is_active', true)->where('approval_status', 'approved')->whereNotNull('price')], 'price')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->take(200)
            ->get()
            ->map(function (Clinic $c) use ($lat, $lng) {
                $distanceKm = null;
                if ($lat !== null && $lng !== null && $c->latitude !== null && $c->longitude !== null) {
                    // Inline Haversine so cards built from the map query (which doesn't
                    // SELECT distance_km) still get a "X km away" badge.
                    $earth = 6371.0;
                    $dLat = deg2rad((float) $c->latitude - $lat);
                    $dLng = deg2rad((float) $c->longitude - $lng);
                    $a = sin($dLat / 2) ** 2
                        + cos(deg2rad($lat)) * cos(deg2rad((float) $c->latitude))
                        * sin($dLng / 2) ** 2;
                    $distanceKm = round($earth * 2 * asin(min(1, sqrt($a))), 1);
                }

                return [
                    'id'            => $c->id,
                    'name'          => $c->name,
                    'slug'          => $c->slug,
                    'lat'           => (float) $c->latitude,
                    'lng'           => (float) $c->longitude,
                    'url'           => route('clinic.show', $c->slug),
                    'logo'          => $c->logo ? Storage::url($c->logo) : null,
                    'city'          => $c->city?->display_name,
                    'snippet'       => Str::limit(trim(strip_tags((string) $c->description)), 110),
                    'rating'        => $c->google_reviews_avg_rating
                        ? round((float) $c->google_reviews_avg_rating, 1)
                        : null,
                    'reviews_count' => (int) ($c->google_reviews_count ?? 0),
                    'categories'    => $c->categories->take(2)->map(fn ($cat) => $cat->display_name)->values(),
                    'min_price'     => $c->min_price !== null ? (float) $c->min_price : null,
                    'directions'    => $c->directionsUrl(),
                    // Either source (admin manual OR package) lights the ribbon.
                    'featured'      => (bool) $c->is_featured || (int) ($c->pkg_featured_in_search ?? 0) === 1,
                    'distance_km'   => $distanceKm,
                ];
            })
            ->values();

        $clinics = $query->paginate(12)->withQueryString();

        // Multi-source impression tracking: clinics that surfaced from a
        // typed query attribute to SEARCH; clinics that surfaced from
        // city/category filters only attribute to FILTER. Knowing the
        // distinction lets the clinic admin see which surface is doing
        // the work.
        $source = $request->filled('q') ? ImpressionSource::SEARCH : ImpressionSource::FILTER;
        app(ImpressionTrackerService::class)->trackManyClinics(
            $clinics->getCollection()->pluck('id')->all(),
            $source,
        );

        // Feed the super-admin "comprehensive user profile" timeline.
        // We only record events when a logged-in user actually filtered
        // or searched — bare /search visits with no params would just
        // be noise (they'd fire on every nav-bar click).
        if (auth('web')->check() && $request->hasAny(['q', 'city', 'category', 'district', 'min_rating', 'price_max'])) {
            app(UserActivityLogger::class)->logSearch(
                $request,
                auth('web')->id(),
                (string) $request->input('q', ''),
                $request->filled('city') ? (int) $request->input('city') : null,
                $request->filled('category') ? (int) $request->input('category') : null,
                $clinics->total(),
            );
        }

        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();
        $cities = City::where('is_active', true)->orderBy('sort_order')->get();

        // Distinct districts for the neighbourhood filter (scoped to the chosen city).
        $districts = Clinic::publiclyVisible()
            ->whereNotNull('district')->where('district', '!=', '')
            ->when($request->filled('city'), fn ($q) => $q->where('city_id', $request->city))
            ->distinct()->orderBy('district')->pluck('district');

        // Pass the user's coordinates (if any) through so the map can render a
        // "you are here" marker and centre itself on the user when present.
        $userLat = $lat;
        $userLng = $lng;

        return view('public.search', compact(
            'clinics', 'categories', 'cities', 'districts',
            'sort', 'mapClinics', 'userLat', 'userLng'
        ));
    }

    /**
     * Lightweight typeahead suggestions for the search box: complexes, services
     * (procedures) and doctors matching the term. Returns JSON.
     */
    public function suggest(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['clinics' => [], 'services' => [], 'doctors' => []]);
        }
        $like = '%' . $term . '%';

        $clinics = Clinic::publiclyVisible()
            ->where('name', 'like', $like)
            ->take(5)->get(['name', 'slug'])
            ->map(fn ($c) => ['label' => $c->name, 'url' => route('clinic.show', $c->slug)])
            ->values();

        $services = \App\Models\Service::where('is_active', true)
            ->where('approval_status', 'approved')
            ->notCatchall()
            ->where('name', 'like', $like)
            ->whereHas('clinic', fn ($q) => $q->publiclyVisible())
            ->with('clinic:id,name,slug')
            ->take(5)->get()
            ->map(fn ($s) => [
                'label' => $s->name,
                'sub'   => $s->clinic?->name,
                'url'   => $s->clinic ? route('clinic.show', $s->clinic->slug) : route('search', ['q' => $s->name]),
            ])->values();

        $doctors = \App\Models\Doctor::where('name', 'like', $like)
            ->whereHas('clinic', fn ($q) => $q->publiclyVisible())
            ->with('clinic:id,name,slug')
            ->take(5)->get()
            ->filter(fn ($d) => $d->clinic !== null)
            ->map(fn ($d) => [
                'label' => $d->name,
                'sub'   => $d->clinic->name,
                'url'   => route('clinic.show', $d->clinic->slug),
            ])->values();

        return response()->json(compact('clinics', 'services', 'doctors'));
    }
}
