<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Concerns\CreatesBooking;
use App\Http\Controllers\Concerns\IdentifiesCustomer;
use App\Http\Controllers\Controller;
use App\Models\ClinicStat;
use App\Models\LandingPage;
use App\Models\LandingPageStat;
use App\Services\LandingPageRenderService;
use Illuminate\Http\Request;

class LandingPageController extends Controller
{
    use IdentifiesCustomer, CreatesBooking;

    public function show(string $slug, LandingPageRenderService $render)
    {
        $page = LandingPage::where('slug', $slug)->firstOrFail();

        // Only published pages within their window are public. A logged-in
        // admin may PREVIEW any page (draft / scheduled / archived) so they can
        // build it before publishing — everyone else gets a 404.
        $isLive = LandingPage::publiclyLive()->whereKey($page->id)->exists();
        $isPreview = false;
        if (! $isLive) {
            abort_unless(auth('admin')->check(), 404);
            $isPreview = true;
        }

        $vm = $render->viewModel($page);
        $vm['isPreview'] = $isPreview;

        // Page-view counters — real visits only (never inflate stats on an
        // admin preview), and never let analytics break the page.
        if (! $isPreview) {
            try {
                $page->increment('total_views');
                LandingPageStat::bump($page->id, 'page_views');
                // Roll a clinic page-view up to the linked complex too, so landing
                // traffic shows in the clinic's own stats (same as a profile view).
                if ($vm['clinic']) {
                    ClinicStat::bump($vm['clinic']->id, 'page_views');
                }
            } catch (\Throwable) {
                // swallow
            }
        }

        return view('public.landing.show', $vm);
    }

    /**
     * Frictionless landing booking. Per the spec, a guest submits name + phone
     * and the request is sent to the complex immediately — no OTP gate. The
     * booking flows through the shared CreatesBooking path so the
     * BookingCustomerLinkObserver creates the per-clinic Customer + an
     * unverified PlatformCustomer keyed by phone. The confirmation page then
     * invites the guest to register (the standard OTP flow links to that same
     * record without creating duplicates).
     */
    public function book(Request $request, string $slug, LandingPageRenderService $render)
    {
        $page = LandingPage::publiclyLive()->where('slug', $slug)->firstOrFail();
        $clinic = $render->contextClinic($page);

        // No bookable complex on this page (e.g. a custom page with no linked
        // clinic) → nothing to submit to.
        abort_if($clinic === null, 404);

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

        $attribution = $this->attribution($request, $page);

        $booking = $this->createBooking($clinic, $validated, $attribution);

        // Conversion → flip the originating visit (if the tracker set one).
        try {
            LandingPageStat::bump($page->id, 'bookings_count');
            $page->increment('total_conversions');
            app(\App\Services\LandingPageTracker::class)->markConvertedForRequest($request, $page, $booking);
        } catch (\Throwable) {
            // tracking is best-effort
        }

        $redirect = redirect()
            ->route('booking.confirmation', $booking->reference_code)
            ->withCookie($this->identityCookie($validated['customer_name'], $validated['customer_phone']));

        // Guest (no account yet) → ask them to register on the confirmation page.
        if (! auth('web')->check()) {
            $redirect->with('lp_register_prompt', $validated['customer_phone']);
        }

        return $redirect;
    }

    /** Build the marketing-attribution array stamped onto the booking. */
    private function attribution(Request $request, LandingPage $page): array
    {
        return [
            'acquisition_source' => 'landing',
            'landing_page_id'    => $page->id,
            // Guest → user_id null (no account); logged-in → their id. The
            // explicit key tells CreatesBooking not to auto-create an account.
            'user_id'            => auth('web')->id(),
            'utm_source'         => $request->input('utm_source'),
            'utm_medium'         => $request->input('utm_medium'),
            'utm_campaign'       => $request->input('utm_campaign'),
            'utm_content'        => $request->input('utm_content'),
            'utm_term'           => $request->input('utm_term'),
        ];
    }
}
