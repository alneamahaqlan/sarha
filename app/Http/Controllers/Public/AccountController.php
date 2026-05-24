<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show()
    {
        $user = auth('web')->user();
        $bookingsCount = $user->bookings()->count();
        $favoritesCount = $user->favorites()->count();

        return view('public.account.profile', compact('user', 'bookingsCount', 'favoritesCount'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . auth('web')->id(),
        ]);

        auth('web')->user()->update($validated);
        return back()->with('success', __('site.account_updated'));
    }

    public function bookings()
    {
        $user = auth('web')->user();
        $bookings = $user
            ->bookings()
            ->with(['clinic.city', 'service'])
            ->latest()
            ->paginate(10);

        $bookingsCount = $user->bookings()->count();
        $favoritesCount = $user->favorites()->count();

        return view('public.account.bookings', compact('bookings', 'user', 'bookingsCount', 'favoritesCount'));
    }

    public function favorites()
    {
        $favorites = auth('web')->user()
            ->favorites()
            ->with(['city', 'categories'])
            ->paginate(12);

        return view('public.account.favorites', compact('favorites'));
    }

    public function toggleFavorite(Clinic $clinic)
    {
        $user = auth('web')->user();

        if ($user->hasFavorited($clinic)) {
            $user->favorites()->detach($clinic->id);
            return back()->with('success', __('site.favorite_removed'));
        }

        $user->favorites()->attach($clinic->id);
        return back()->with('success', __('site.favorite_added'));
    }
}
