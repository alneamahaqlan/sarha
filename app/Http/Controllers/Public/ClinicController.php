<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\PriceQuoteRequest;
use Illuminate\Http\Request;

class ClinicController extends Controller
{
    public function show(string $slug)
    {
        $clinic = Clinic::publiclyVisible()
            ->where('slug', $slug)
            ->with([
                'city',
                'categories',
                'services' => fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'articles' => fn($q) => $q->where('is_published', true)->latest()->limit(6),
                'googleReviews' => fn($q) => $q->where('is_visible', true)->latest('reviewed_at')->limit(20),
                'workingHours' => fn($q) => $q->orderBy('day_of_week'),
            ])
            ->withAvg('googleReviews', 'rating')
            ->withCount('googleReviews')
            ->firstOrFail();

        $similarClinics = Clinic::publiclyVisible()
            ->where('id', '!=', $clinic->id)
            ->where('city_id', $clinic->city_id)
            ->whereHas('categories', fn($q) => $q->whereIn('categories.id', $clinic->categories->pluck('id')))
            ->with(['city', 'categories'])
            ->rankedForListing()
            ->take(3)
            ->get();

        return view('public.clinic', compact('clinic', 'similarClinics'));
    }

    public function bookingForm(string $slug, Request $request)
    {
        $clinic = Clinic::publiclyVisible()
            ->where('slug', $slug)
            ->with(['services' => fn($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->firstOrFail();

        $service = $request->filled('service')
            ? $clinic->services->firstWhere('id', $request->integer('service'))
            : null;

        return view('public.booking-form', compact('clinic', 'service'));
    }

    public function book(Request $request, string $slug)
    {
        $clinic = Clinic::publiclyVisible()->where('slug', $slug)->firstOrFail();

        if (auth('web')->check() && ! auth('web')->user()->is_active) {
            return back()->withErrors([
                'account' => __('site.account_blocked'),
            ])->withInput();
        }

        $validated = $request->validate([
            'customer_name'  => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20|regex:/^05\d{8}$/',
            'service_id'     => 'nullable|exists:services,id',
            'notes'          => 'nullable|string|max:1000',
        ], [
            'customer_phone.regex' => __('site.phone_invalid'),
        ]);

        $booking = Booking::create([
            'clinic_id'      => $clinic->id,
            'user_id'        => auth('web')->id(),
            'service_id'     => $validated['service_id'] ?? null,
            'customer_name'  => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'notes'          => $validated['notes'] ?? null,
            'status'         => 'new',
            'source'         => 'website',
        ]);

        return redirect()->route('booking.confirmation', $booking->reference_code);
    }

    public function bookingConfirmation(string $reference)
    {
        $booking = Booking::with(['clinic.city', 'service'])
            ->where('reference_code', $reference)
            ->firstOrFail();

        return view('public.booking-confirmation', compact('booking'));
    }

    public function priceQuote(Request $request, string $slug)
    {
        $clinic = Clinic::publiclyVisible()->where('slug', $slug)->firstOrFail();

        if (auth('web')->check() && ! auth('web')->user()->is_active) {
            return back()->withErrors([
                'account' => __('site.account_blocked'),
            ])->withInput();
        }

        $validated = $request->validate([
            'customer_name'  => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20|regex:/^05\d{8}$/',
            'service_name'   => 'required|string|max:255',
            'description'    => 'required|string|min:10|max:2000',
        ], [
            'customer_phone.regex' => __('site.phone_invalid'),
        ]);

        PriceQuoteRequest::create([
            'clinic_id'      => $clinic->id,
            'user_id'        => auth('web')->id(),
            'customer_name'  => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'service_name'   => $validated['service_name'],
            'description'    => $validated['description'],
            'status'         => 'new',
        ]);

        return back()->with('success', __('site.price_quote_sent'));
    }
}
