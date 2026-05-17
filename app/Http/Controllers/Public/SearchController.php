<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Clinic;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public const SORT_OPTIONS = ['featured', 'top_rated', 'cheapest', 'most_booked'];

    public function index(Request $request)
    {
        $query = Clinic::publiclyVisible()->with(['city', 'categories']);

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

        $sort = in_array($request->input('sort'), self::SORT_OPTIONS, true)
            ? $request->input('sort')
            : 'featured';

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
            default => $query->rankedForListing(),
        };

        $clinics = $query->paginate(12)->withQueryString();
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();
        $cities = City::where('is_active', true)->orderBy('sort_order')->get();

        return view('public.search', compact('clinics', 'categories', 'cities', 'sort'));
    }
}
