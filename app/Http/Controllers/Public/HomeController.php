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
        $featuredClinics = Clinic::where('status', 'active')
            ->where('is_featured', true)
            ->with(['city', 'categories'])
            ->latest()
            ->take(8)
            ->get();

        return view('public.home', compact('categories', 'cities', 'featuredClinics'));
    }
}
