<?php

namespace App\Http\Controllers\Public;

use App\Enums\ImpressionSource;
use App\Http\Controllers\Concerns\CreatesBooking;
use App\Http\Controllers\Concerns\IdentifiesCustomer;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\ClinicStat;
use App\Models\OtpCode;
use App\Models\PriceQuoteRequest;
use App\Models\Relative;
use App\Services\ClinicPageBuilderService;
use App\Services\FeatureGate;
use App\Services\ImpressionTrackerService;
use App\Services\SimilarityService;
use App\Services\SmsService;
use App\Services\UserActivityLogger;
use Illuminate\Http\Request;

class ClinicController extends Controller
{
    use IdentifiesCustomer, CreatesBooking;

    public function show(string $slug, ClinicPageBuilderService $builder, FeatureGate $gate, SimilarityService $similarity)
    {
        $clinic = Clinic::publiclyVisible()
            ->where('slug', $slug)
            ->with([
                'city',
                'categories',
                // Live Instagram-style stories (ring around the logo).
                'stories',
                'services' => fn($q) => $q->where('is_active', true)->where('approval_status', 'approved')->orderBy('sort_order'),
                'services.inlineOffer',
                // Specialty (category) ids power the click-to-filter chips in
                // the hero — every filterable entity carries its own ids.
                'services.categories:id',
                // Sub-clinics + their active services for the nested services tab.
                'subClinics.category',
                'subClinics.services' => fn($q) => $q->where('is_active', true)->where('approval_status', 'approved')->orderBy('sort_order'),
                'subClinics.services.inlineOffer',
                'subClinics.services.categories:id',
                // Doctors showcase + packages (with their services) for their tabs.
                'doctors.subClinic',
                'doctors.services.categories:id',
                'packages.services' => fn($q) => $q->where('is_active', true)->where('approval_status', 'approved'),
                'packages.services.categories:id',
                // Promotional offers — model filter further narrows to the
                // running window in the blade so the relation hands back
                // the full list (active+scheduled+expired) and the view
                // shows only what's live now.
                'offers' => fn($q) => $q->orderByDesc('is_featured')->orderByDesc('starts_at'),
                'offers.service:id,name,price,image',
                'offers.service.categories:id',
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

        // Record a page view (never let stats break the page).
        try {
            ClinicStat::bump($clinic->id, 'page_views');
            // Every profile open also counts as an impression, regardless of
            // how the visitor arrived (direct link, share, preview, …).
            app(ImpressionTrackerService::class)->trackClinic($clinic->id, ImpressionSource::PROFILE);
        } catch (\Throwable $e) {
            // swallow — analytics must not affect the visitor experience
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

        // Package-driven section gating: even if an admin enabled a
        // section, the clinic's current package can lock it. Filter
        // here so both the tabs row and the tab content drop out.
        $allowOffers  = $gate->canPublishOffers($clinic);
        $allowDoctors = $gate->canShowDoctorsAndBeforeAfter($clinic);
        $pageSections = $pageSections->reject(function ($section) use ($allowOffers, $allowDoctors) {
            return match ($section->key) {
                'offers', 'packages'         => ! $allowOffers,
                'doctors', 'before_after'    => ! $allowDoctors,
                default                      => false,
            };
        });

        // Zero-out the relations the package forbids so any leftover
        // partial that reads $clinic->doctors / etc. sees an empty
        // collection (defence-in-depth — the @if on $pageSections
        // already hides the tab, but stale code that bypasses it
        // still won't leak premium-only content).
        if (! $allowOffers) {
            $clinic->setRelation('offers', collect());
            $clinic->setRelation('packages', collect());
        }
        if (! $allowDoctors) {
            $clinic->setRelation('doctors', collect());
            $clinic->setRelation('beforeAfterPhotos', collect());
        }

        // Verified badge — purely package-driven; rendered in the
        // clinic header next to the name. Stored as a flat attribute
        // so Blade reads it as `$clinic->is_verified_badge` without
        // resolving the package relation per request.
        $clinic->setAttribute('is_verified_badge', $gate->hasVerifiedBadge($clinic));

        // "Similar complexes" strip at the foot of the page (same city +
        // overlapping specialties, other complexes).
        $similarClinics = $similarity->similarClinics($clinic);

        return view('public.clinic', compact('clinic', 'similarClinics', 'pageSections', 'builder'));
    }

    public function bookingForm(string $slug, Request $request)
    {
        $clinic = Clinic::publiclyVisible()
            ->where('slug', $slug)
            ->with(['services' => fn($q) => $q->where('is_active', true)->where('approval_status', 'approved')->orderBy('sort_order')])
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
        // Per-phone send guard stops a victim's number from being SMS-bombed.
        if ($wait = OtpCode::throttleSend($validated['customer_phone'], 'booking')) {
            return back()
                ->withErrors(['customer_phone' => __('site.otp_too_many', ['seconds' => $wait])])
                ->withInput();
        }
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

    public function bookingConfirmation(string $reference)
    {
        $booking = Booking::with(['clinic.city', 'service'])
            ->where('reference_code', $reference)
            ->firstOrFail();

        // This is the conversion page but has no {slug} route param, so the
        // tracking middleware can't resolve it — bind the context from the
        // booking's clinic here (sensitive: URL is sanitised for pixels).
        $trackingCtx = app(\App\Services\Tracking\TrackingContextResolver::class)
            ->forClinic($booking->clinic, true);
        app()->instance(\App\Services\Tracking\TrackingContext::class, $trackingCtx);

        // Advanced matching (Layer B): a SHA-256 hashed phone for Meta, ONLY
        // when the clinic opted in. Computed server-side so no raw value is
        // added by us; the view fires it only after the visitor consents.
        // Never includes the medical service — phone hash only.
        $amMetaId = null;
        $amPhoneHash = null;
        if ($trackingCtx->advancedMatching && ($mid = $trackingCtx->metaPixelId())) {
            $digits = preg_replace('/\D/', '', (string) $booking->customer_phone);
            if (str_starts_with($digits, '0') && strlen($digits) === 10) {
                $digits = '966' . substr($digits, 1); // 05XXXXXXXX -> 9665XXXXXXXX
            }
            if ($digits !== '') {
                $amMetaId = $mid;
                $amPhoneHash = hash('sha256', $digits);
            }
        }

        return view('public.booking-confirmation', compact('booking', 'amMetaId', 'amPhoneHash'));
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
