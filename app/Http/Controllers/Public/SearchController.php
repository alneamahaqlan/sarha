<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Clinic;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = Clinic::where('status', 'active')->with(['city', 'categories']);

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

        $clinics = $query->paginate(12)->withQueryString();
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();
        $cities = City::where('is_active', true)->orderBy('sort_order')->get();

        return view('public.search', compact('clinics', 'categories', 'cities'));
    }
}
