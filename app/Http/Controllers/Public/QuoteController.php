<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Concerns\IdentifiesCustomer;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Clinic;
use App\Models\ClinicStat;
use App\Models\OtpCode;
use App\Models\PriceQuoteRequest;
use App\Services\NotificationService;
use App\Services\SmsService;
use Illuminate\Http\Request;

/**
 * Broadcast price-quote requests: a customer asks once, and every clinic in the
 * targeted cities can reply. Replies can be private (only the requester) or
 * public (shown on the public quotes board).
 */
class QuoteController extends Controller
{
    use IdentifiesCustomer;

    public function requestForm(Request $request)
    {
        $cities = City::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'name_en']);
        $identity = $this->customerIdentity($request);

        return view('public.quote-form', compact('cities', 'identity'));
    }

    public function store(Request $request)
    {
        if (auth('web')->check() && ! auth('web')->user()->is_active) {
            return back()->withErrors(['account' => __('site.account_blocked')])->withInput();
        }

        $validated = $this->validateQuote($request);

        if ($this->customerVerified($request, $validated['customer_phone'])) {
            // A returning device (cookie match) skips OTP but isn't logged in yet.
            // Log the customer in so they own the new request and can view
            // quotes.show — otherwise the owner check there fails and aborts 404.
            if (! auth('web')->check()) {
                $user = $this->resolveCustomerUser($validated['customer_name'], $validated['customer_phone']);
                if (! $user->is_active) {
                    return back()->withErrors(['account' => __('site.account_blocked')])->withInput();
                }
                auth('web')->login($user, true);
            }

            $quote = $this->createQuote($validated);

            return redirect()->route('quotes.show', $quote->id)
                ->with('success', __('site.price_quote_sent'))
                ->withCookie($this->identityCookie($validated['customer_name'], $validated['customer_phone']));
        }

        // Per-phone send guard against SMS-bombing a victim's number.
        if ($wait = OtpCode::throttleSend($validated['customer_phone'], 'quote')) {
            return back()
                ->withErrors(['customer_phone' => __('site.otp_too_many', ['seconds' => $wait])])
                ->withInput();
        }
        $otp = OtpCode::generate($validated['customer_phone'], 'quote');
        app(SmsService::class)->send($validated['customer_phone'], __('site.otp_sms', ['code' => $otp->code]));
        session()->put('pending_quote', $validated);

        $redirect = back()
            ->with('otp_required', true)
            ->with('otp_phone', $validated['customer_phone'])
            ->withInput();

        if (app()->environment('local')) {
            $redirect->with('dev_code', $otp->code);
        }

        return $redirect;
    }

    public function storeVerify(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);

        $pending = session('pending_quote');
        if (! $pending) {
            return redirect()->route('quotes.request')->withErrors(['code' => __('site.booking_session_expired')]);
        }

        $otp = OtpCode::where('phone', $pending['customer_phone'])
            ->where('code', $request->string('code'))
            ->where('purpose', 'quote')
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
            session()->forget('pending_quote');
            return redirect()->route('quotes.request')->withErrors(['account' => __('site.account_blocked')]);
        }

        auth('web')->login($user, true);
        $quote = $this->createQuote($pending);
        session()->forget('pending_quote');

        return redirect()->route('quotes.show', $quote->id)
            ->with('success', __('site.price_quote_sent'))
            ->withCookie($this->identityCookie($pending['customer_name'], $pending['customer_phone']));
    }

    /** Public board: requests that have at least one public reply. */
    public function board()
    {
        $requests = PriceQuoteRequest::query()
            ->whereHas('publicReplies')
            ->with(['cities:id,name,name_en', 'publicReplies.clinic:id,name,slug'])
            ->withCount('publicReplies')
            ->latest()
            ->paginate(12);

        return view('public.quotes-board', compact('requests'));
    }

    public function show(PriceQuoteRequest $quoteRequest)
    {
        $quoteRequest->load(['cities:id,name,name_en', 'replies.clinic:id,name,slug']);

        $isOwner = auth('web')->id() && auth('web')->id() === $quoteRequest->user_id;
        $hasPublicReplies = $quoteRequest->publicReplies()->exists();

        // A private request (no public replies yet) is viewable only by its owner.
        if (! $isOwner && ! $hasPublicReplies) {
            // A guest may in fact be the owner once they log in — send them to
            // login and bring them back here, instead of a dead-end 404.
            if (! auth('web')->check()) {
                return redirect()->guest(route('login'));
            }
            abort(404);
        }

        $replies = $isOwner
            ? $quoteRequest->replies
            : $quoteRequest->replies->where('is_public', true);

        return view('public.quote-show', compact('quoteRequest', 'replies', 'isOwner'));
    }

    private function validateQuote(Request $request): array
    {
        return $request->validate([
            'customer_name'  => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20|regex:/^05\d{8}$/',
            'service_name'   => 'required|string|max:255',
            'description'    => 'required|string|min:10|max:2000',
            'city_ids'       => 'required|array|min:1',
            'city_ids.*'     => 'integer|exists:cities,id',
        ], [
            'customer_phone.regex' => __('site.phone_invalid'),
            'city_ids.required'    => __('site.quote_cities_required'),
        ]);
    }

    private function createQuote(array $data): PriceQuoteRequest
    {
        $userId = auth('web')->id()
            ?? $this->resolveCustomerUser($data['customer_name'], $data['customer_phone'])->id;

        $quote = PriceQuoteRequest::create([
            'clinic_id'      => null,
            'user_id'        => $userId,
            'customer_name'  => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'service_name'   => $data['service_name'],
            'description'    => $data['description'],
            'status'         => 'new',
        ]);

        $quote->cities()->sync($data['city_ids']);

        // Every clinic in the targeted cities "received" this request.
        Clinic::publiclyVisible()->whereIn('city_id', $data['city_ids'])->pluck('id')
            ->each(fn ($cid) => ClinicStat::bump($cid, 'quote_requests_count'));

        // Notify the targeted complexes (cities are attached now).
        app(NotificationService::class)->broadcastQuoteToCityClinics($quote);

        return $quote;
    }
}
