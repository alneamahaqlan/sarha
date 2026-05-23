<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Clinic;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public const SORT_OPTIONS = ['featured', 'top_rated', 'cheapest', 'most_booked', 'nearest'];

    public function index(Request $request)
    {
        $query = Clinic::publiclyVisible()
            ->with(['city', 'categories'])
            ->withAvg('googleReviews', 'rating')
            ->withCount('bookings')
            ->withMin(['services as min_price' => fn($q) => $q->where('is_active', true)->whereNotNull('price')], 'price');

        if ($request->filled('city')) {
            $query->where('city_id', $request->city);
        }

        if ($request->filled('category')) {
            $query->whereHas('categories', fn($q) => $q->where('categories.id', $request->category));
        }

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(fn($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")
            );
        }

        if ($request->filled('featured')) {
            $query->where('is_featured', true);
        }

        // Snapshot the filtered (pre-sort) query for the map markers so they reflect
        // the active filters (#62) without the sort-specific selectRaw/order clauses.
        $mapQuery = clone $query;

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
                ->orderByDesc('is_featured')
                ->orderByDesc('google_reviews_avg_rating'),
            'cheapest' => $query
                ->withMin(['services as min_price' => fn($q) => $q->where('is_active', true)->whereNotNull('price')], 'price')
                ->orderByDesc('is_featured')
                ->orderBy('min_price'),
            'most_booked' => $query
                ->withCount('bookings')
                ->orderByDesc('is_featured')
                ->orderByDesc('bookings_count'),
            'nearest' => $query
                // Haversine distance (km); clinics with null coords sort last.
                ->selectRaw(
                    '*, CASE WHEN latitude IS NULL OR longitude IS NULL THEN NULL ELSE '
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
        // the current page) so markers stay in sync with active filters (#62).
        $mapClinics = $mapQuery
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->take(200)
            ->get(['id', 'name', 'slug', 'latitude', 'longitude'])
            ->map(fn (Clinic $c) => [
                'id'   => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'lat'  => (float) $c->latitude,
                'lng'  => (float) $c->longitude,
            ])
            ->values();

        $clinics = $query->paginate(12)->withQueryString();
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();
        $cities = City::where('is_active', true)->orderBy('sort_order')->get();

        return view('public.search', compact('clinics', 'categories', 'cities', 'sort', 'mapClinics'));
    }
}
