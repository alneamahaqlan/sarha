<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Clinic;

class HomeController extends Controller
{
    public function index()
    {
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();
        $cities = City::where('is_active', true)->orderBy('sort_order')->get();
        $featuredClinics = Clinic::publiclyVisible()
            ->where('is_featured', true)
            ->with(['city', 'categories'])
            ->rankedForListing()
            ->take(8)
            ->get();

        $topRatedClinics = Clinic::publiclyVisible()
            ->with(['city', 'categories'])
            ->whereHas('googleReviews')
            ->withAvg('googleReviews', 'rating')
            ->withCount('googleReviews')
            ->orderByDesc('google_reviews_avg_rating')
            ->take(3)
            ->get();

        $bestPricedClinics = Clinic::publiclyVisible()
            ->with(['city', 'categories'])
            ->whereHas('services', fn($q) => $q->where('is_active', true)->whereNotNull('price'))
            ->withMin(['services as min_price' => fn($q) => $q->where('is_active', true)->whereNotNull('price')], 'price')
            ->orderBy('min_price')
            ->take(3)
            ->get();

        // Geocoded clinics for the homepage map (id, name, slug, lat, lng). Null coords excluded.
        $mapClinics = Clinic::publiclyVisible()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('is_featured')
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

        return view('public.home', compact('categories', 'cities', 'featuredClinics', 'topRatedClinics', 'bestPricedClinics', 'mapClinics'));
    }
}
