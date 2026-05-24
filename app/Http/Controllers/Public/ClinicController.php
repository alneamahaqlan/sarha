<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Concerns\IdentifiesCustomer;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\ClinicStat;
use App\Models\OtpCode;
use App\Models\PriceQuoteRequest;
use App\Services\SmsService;
use Illuminate\Http\Request;

class ClinicController extends Controller
{
    use IdentifiesCustomer;

    public function show(string $slug)
    {
        $clinic = Clinic::publiclyVisible()
            ->where('slug', $slug)
            ->with([
                'city',
                'categories',
                'services' => fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
                // Sub-clinics + their active services for the nested services tab.
                'subClinics.category',
                'subClinics.services' => fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
                // Doctors showcase + packages (with their services) for their tabs.
                'doctors',
                'packages.services' => fn($q) => $q->where('is_active', true),
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

        // Record a page view (never let stats break the page).
        try {
            ClinicStat::bump($clinic->id, 'page_views');
        } catch (\Throwable $e) {
            // swallow — analytics must not affect the visitor experience
        }

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

        // Returning customer's saved identity (from a prior verified booking).
        $identity = $this->customerIdentity($request);

        return view('public.booking-form', compact('clinic', 'service', 'identity'));
    }

    public function book(Request $request, string $slug)
    {
        $clinic = Clinic::publiclyVisible()->where('slug', $slug)->firstOrFail();

        if (auth('web')->check() && ! auth('web')->user()->is_active) {
            return back()->withErrors(['account' => __('site.account_blocked')])->withInput();
        }

        $validated = $request->validate([
            'customer_name'  => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20|regex:/^05\d{8}$/',
            'service_id'     => 'nullable|exists:services,id',
            'notes'          => 'nullable|string|max:1000',
        ], [
            'customer_phone.regex' => __('site.phone_invalid'),
        ]);

        // A logged-in customer, or a returning device whose saved phone matches,
        // skips OTP. First-time guests must verify once (registers them).
        if ($this->customerVerified($request, $validated['customer_phone'])) {
            $booking = $this->createBooking($clinic, $validated);

            return redirect()->route('booking.confirmation', $booking->reference_code)
                ->withCookie($this->identityCookie($validated['customer_name'], $validated['customer_phone']));
        }

        // First-time: generate + send OTP, stash the pending booking, show the step.
        $otp = OtpCode::generate($validated['customer_phone'], 'booking');
        app(SmsService::class)->send($validated['customer_phone'], __('site.otp_sms', ['code' => $otp->code]));
        session()->put('pending_booking', $validated + ['slug' => $slug]);

        $redirect = back()
            ->with('otp_required', true)
            ->with('otp_phone', $validated['customer_phone'])
            ->withInput();

        if (app()->environment('local')) {
            $redirect->with('dev_code', $otp->code);
        }

        return $redirect;
    }

    /** Verify the one-time code for a first booking, register the customer, then store the booking. */
    public function bookVerify(Request $request, string $slug)
    {
        $request->validate(['code' => 'required|string|size:6']);

        $pending = session('pending_booking');
        if (! $pending || ($pending['slug'] ?? null) !== $slug) {
            return redirect()->route('clinic.book.form', $slug)
                ->withErrors(['code' => __('site.booking_session_expired')]);
        }

        $clinic = Clinic::publiclyVisible()->where('slug', $slug)->firstOrFail();

        $otp = OtpCode::where('phone', $pending['customer_phone'])
            ->where('code', $request->string('code'))
            ->where('purpose', 'booking')
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otp) {
            return back()
                ->with('otp_required', true)
                ->with('otp_phone', $pending['customer_phone'])
                ->withErrors(['code' => __('site.otp_invalid')]);
        }

        $otp->update(['is_used' => true]);

        $user = $this->resolveCustomerUser($pending['customer_name'], $pending['customer_phone']);

        if (! $user->is_active) {
            session()->forget('pending_booking');
            return redirect()->route('clinic.book.form', $slug)->withErrors(['account' => __('site.account_blocked')]);
        }

        auth('web')->login($user, true);

        $booking = $this->createBooking($clinic, $pending);
        session()->forget('pending_booking');

        return redirect()->route('booking.confirmation', $booking->reference_code)
            ->withCookie($this->identityCookie($pending['customer_name'], $pending['customer_phone']));
    }

    /** Create the booking, attaching/creating the customer user for persistence. */
    private function createBooking(Clinic $clinic, array $data): Booking
    {
        $userId = auth('web')->id()
            ?? $this->resolveCustomerUser($data['customer_name'], $data['customer_phone'])->id;

        $booking = Booking::create([
            'clinic_id'      => $clinic->id,
            'user_id'        => $userId,
            'service_id'     => $data['service_id'] ?? null,
            'customer_name'  => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'notes'          => $data['notes'] ?? null,
            'status'         => 'new',
            'source'         => 'website',
        ]);

        ClinicStat::bump($clinic->id, 'bookings_count');

        return $booking;
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

        ClinicStat::bump($clinic->id, 'quote_requests_count');

        return back()->with('success', __('site.price_quote_sent'));
    }
}
