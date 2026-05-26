<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Complaint;
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

    public function quotes()
    {
        $user = auth('web')->user();
        $quotes = $user->priceQuoteRequests()
            ->with('cities:id,name,name_en')
            ->withCount('replies')
            ->latest()
            ->paginate(10);

        $bookingsCount = $user->bookings()->count();
        $favoritesCount = $user->favorites()->count();

        return view('public.account.quotes', compact('quotes', 'user', 'bookingsCount', 'favoritesCount'));
    }

    public function complaints()
    {
        $user = auth('web')->user();

        $complaints = Complaint::where('user_id', $user->id)
            ->where('source', 'customer')
            ->with('clinic:id,name')
            ->latest()
            ->paginate(10);

        // Clinics the customer has dealt with — offered as the optional target.
        $clinics = Clinic::whereIn('id', $user->bookings()->select('clinic_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('public.account.complaints', compact('complaints', 'clinics', 'user'));
    }

    public function storeComplaint(Request $request)
    {
        $user = auth('web')->user();

        $validated = $request->validate([
            'type'        => 'required|in:' . implode(',', Complaint::TYPES),
            'clinic_id'   => 'nullable|integer|exists:clinics,id',
            'subject'     => 'required|string|max:255',
            'description' => 'required|string|min:10|max:2000',
        ]);

        Complaint::create([
            'source'         => 'customer',
            'user_id'        => $user->id,
            'clinic_id'      => $validated['clinic_id'] ?? null,
            'customer_name'  => $user->name ?: __('site.account_customer'),
            'customer_phone' => $user->phone ?? '',
            'customer_email' => $user->email,
            'type'           => $validated['type'],
            'priority'       => 'medium',
            'status'         => 'new',
            'subject'        => $validated['subject'],
            'description'    => $validated['description'],
        ]);

        return back()->with('success', __('site.complaint_sent'));
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
