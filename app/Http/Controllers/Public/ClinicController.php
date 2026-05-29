<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Concerns\IdentifiesCustomer;
use App\Http\Controllers\Controller;
use App\Enums\ImpressionSource;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\ClinicStat;
use App\Models\OtpCode;
use App\Models\PriceQuoteRequest;
use App\Models\Relative;
use App\Services\ClinicPageBuilderService;
use App\Services\ImpressionTrackerService;
use App\Services\SmsService;
use App\Services\UserActivityLogger;
use Illuminate\Http\Request;

class ClinicController extends Controller
{
    use IdentifiesCustomer;

    public function show(string $slug, ClinicPageBuilderService $builder)
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
                'doctors.subClinic',
                'packages.services' => fn($q) => $q->where('is_active', true),
                // Promotional offers — model filter further narrows to the
                // running window in the blade so the relation hands back
                // the full list (active+scheduled+expired) and the view
                // shows only what's live now.
                'offers' => fn($q) => $q->orderByDesc('is_featured')->orderByDesc('starts_at'),
                'offers.service:id,name,price,image',
                // Before/after gallery (with optional service/sub-clinic links).
                'beforeAfterPhotos.service:id,name',
                'beforeAfterPhotos.subClinic:id,name,name_en',
                'articles' => fn($q) => $q->where('is_published', true)->latest()->limit(6),
                'googleReviews' => fn($q) => $q->where('is_visible', true)->latest('reviewed_at')->limit(20),
                'workingHours' => fn($q) => $q->orderBy('day_of_week'),
            ])
            ->withAvg('googleReviews', 'rating')
            ->withCount('googleReviews')
            ->firstOrFail();

        // "Similar services in the same city" — replaces the previous
        // similar-clinics block. Aggregates services from OTHER clinics in
        // this city whose category set overlaps the current clinic's
        // specialty coverage, ordered cheapest-first so the customer can
        // price-compare without leaving the page.
        $thisCategoryIds = $clinic->categories->pluck('id');
        $similarServices = $thisCategoryIds->isNotEmpty()
            ? \App\Models\Service::query()
                ->with(['clinic:id,name,slug,city_id', 'clinic.city:id,name', 'categories:id,name,emoji'])
                ->where('is_active', true)
                ->whereNotNull('price')
                ->where('clinic_id', '!=', $clinic->id)
                ->whereHas('clinic', fn ($q) => $q->publiclyVisible()->where('city_id', $clinic->city_id))
                ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $thisCategoryIds))
                ->orderBy('price')
                ->take(8)
                ->get()
            : collect();

        // Record a page view (never let stats break the page).
        try {
            ClinicStat::bump($clinic->id, 'page_views');
        } catch (\Throwable $e) {
            // swallow — analytics must not affect the visitor experience
        }

        // Multi-source impression tracking: every service surfaced in
        // the "similar services" block counts as an impression for that
        // service AND (via cascade) its owning clinic. The current
        // clinic itself is NOT bumped — this is a direct page visit,
        // already covered by `page_views` above.
        if ($similarServices->isNotEmpty()) {
            app(ImpressionTrackerService::class)
                ->trackManyServices($similarServices->all(), ImpressionSource::SIMILAR);
        }

        // Feed the super-admin profile timeline.
        if (auth('web')->check()) {
            app(UserActivityLogger::class)->logClinicView(
                request(), auth('web')->id(), $clinic->id, $clinic->slug,
            );
        }

        // Per-clinic Page Builder config: which of the 14 sections are
        // active, their order, and any title/limit overrides. Lazy-seeds
        // defaults on first visit so the X existing clinics keep working.
        $pageSections = $builder->forPublic($clinic->id);

        return view('public.clinic', compact('clinic', 'similarServices', 'pageSections', 'builder'));
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

        // Authenticated booker → load their saved relatives so the
        // "book for someone else" UI can show cards. Guests get an
        // empty collection and the radio stays hidden.
        $relatives = auth('web')->check()
            ? auth('web')->user()->relatives()->latest('id')->get()
            : collect();
        $relativeTypes = Relative::TYPES;

        return view('public.booking-form', compact(
            'clinic', 'service', 'identity', 'relatives', 'relativeTypes',
        ));
    }

    public function book(Request $request, string $slug)
    {
        $clinic = Clinic::publiclyVisible()->where('slug', $slug)->firstOrFail();

        if (auth('web')->check() && ! auth('web')->user()->is_active) {
            return back()->withErrors(['account' => __('site.account_blocked')])->withInput();
        }

        // Mode = who is the patient?
        //   self                → booker == patient (default; only path open to guests)
        //   relative_existing   → pick one of the booker's saved relatives
        //   relative_new        → enter relative inline, save it + use it
        $bookerUser = auth('web')->user();
        $mode = $request->input('booking_for', 'self');
        if (! in_array($mode, ['self', 'relative_existing', 'relative_new'], true) || ! $bookerUser) {
            $mode = 'self';
        }

        $rules = [
            'service_id' => 'nullable|exists:services,id',
            'notes'      => 'nullable|string|max:1000',
        ];

        if ($mode === 'self') {
            $rules['customer_name']  = 'required|string|max:255';
            $rules['customer_phone'] = 'required|string|max:20|regex:/^05\d{8}$/';
        } elseif ($mode === 'relative_existing') {
            $rules['relative_id'] = 'required|integer|exists:relatives,id';
        } else {
            $rules['relative.name']               = 'required|string|max:255';
            $rules['relative.relationship_type']  = 'required|string|in:' . implode(',', Relative::TYPES);
            $rules['relative.relationship_label'] = 'nullable|string|max:50';
            $rules['relative.phone']              = 'required|string|max:20|regex:/^05\d{8}$/';
        }

        $validated = $request->validate($rules, [
            'customer_phone.regex' => __('site.phone_invalid'),
            'relative.phone.regex' => __('site.phone_invalid'),
        ]);

        // Translate the chosen mode into (customer_name, customer_phone,
        // booker_user_id, relative_id). For new-relative mode, this is
        // also the point we persist the Relative row.
        $resolved = $this->resolveBookingTarget($mode, $bookerUser, $validated, $request);
        if ($resolved instanceof \Illuminate\Http\RedirectResponse) {
            return $resolved;
        }
        $validated = array_merge($validated, $resolved);

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

    /**
     * Resolve the chosen mode into the booker / patient / relative tuple
     * the rest of the pipeline needs. For 'relative_new', this is also
     * where the Relative row is persisted (after validation, before the
     * Booking is created — order keeps the relative around even if the
     * OTP step is interrupted, which matches the "auto-save on confirm"
     * spec since the cap was already enforced).
     *
     * Returns either a partial array (customer_name, customer_phone,
     * booker_user_id, relative_id) to be merged into $validated, OR a
     * RedirectResponse when a soft error (cap reached, stale relative
     * id) needs to be surfaced via flash.
     */
    private function resolveBookingTarget(string $mode, ?\App\Models\User $bookerUser, array $validated, Request $request): array|\Illuminate\Http\RedirectResponse
    {
        if ($mode === 'self') {
            return [
                // Spec: booker_user_id is null for self-bookings. user_id
                // already carries the booker, so this column only flips on
                // when the booking is placed on behalf of a relative.
                'booker_user_id' => null,
                'relative_id'    => null,
                'customer_name'  => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
            ];
        }

        if ($mode === 'relative_existing') {
            $relative = $bookerUser->relatives()->find($validated['relative_id']);
            if (! $relative) {
                return back()
                    ->withErrors(['relative_id' => __('site.relative_not_found')])
                    ->withInput();
            }
            return [
                'booker_user_id' => $bookerUser->id,
                'relative_id'    => $relative->id,
                'customer_name'  => $relative->name,
                'customer_phone' => $relative->phone,
            ];
        }

        // relative_new: hard cap of 3 is enforced here so the form never
        // creates a fourth row even if the front-end button slipped through.
        if ($bookerUser->relatives()->count() >= Relative::MAX_PER_USER) {
            return back()
                ->withErrors(['relative.name' => __('site.relative_max_reached')])
                ->withInput();
        }

        $relative = $bookerUser->relatives()->create([
            'name'               => $validated['relative']['name'],
            'relationship_type'  => $validated['relative']['relationship_type'],
            'relationship_label' => $validated['relative']['relationship_label'] ?? null,
            'phone'              => $validated['relative']['phone'],
        ]);

        return [
            'booker_user_id' => $bookerUser->id,
            'relative_id'    => $relative->id,
            'customer_name'  => $relative->name,
            'customer_phone' => $relative->phone,
        ];
    }

    /** Create the booking, attaching/creating the customer user for persistence. */
    private function createBooking(Clinic $clinic, array $data): Booking
    {
        // user_id is the account that OWNS the booking (where it shows
        // up under /account/bookings). For relative-mode it's the
        // booker, not the relative — spec: "القريب نفسه لا يرى الحجز
        // في حسابه (لتجنّب الازدواجية)".
        $relativeId = $data['relative_id'] ?? null;
        $bookerId   = $data['booker_user_id'] ?? null;

        if ($relativeId) {
            // Relative-mode always carries booker_user_id (resolveBookingTarget
            // sets both). user_id = the booker so the booking is filed
            // under the right account.
            $userId = $bookerId;
        } else {
            // Self-mode: auth user OR a freshly-resolved customer from
            // the typed phone (guest path, same as before).
            $userId = auth('web')->id()
                ?? $this->resolveCustomerUser($data['customer_name'], $data['customer_phone'])->id;
        }

        $booking = Booking::create([
            'clinic_id'      => $clinic->id,
            'user_id'        => $userId,
            'booker_user_id' => $bookerId,
            'relative_id'    => $data['relative_id'] ?? null,
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
