<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\Otp\OtpDispatcher;
use App\Services\UserActivityLogger;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    public function showLogin(Request $request)
    {
        // Remember the page the guest was browsing so we can return them there
        // after a successful OTP login (verifyOtp uses redirect()->intended()).
        // Prefer an explicit ?redirect= param, then fall back to the referrer.
        $target = $request->query('redirect') ?: $request->headers->get('referer');
        if ($this->isSafeReturnUrl($target)) {
            $request->session()->put('url.intended', $target);
        }

        return view('auth.login');
    }

    /**
     * Only return-to URLs on this site, and never an auth page (which would
     * bounce the user back to the login screen instead of their content).
     */
    protected function isSafeReturnUrl(?string $url): bool
    {
        if (! $url) {
            return false;
        }

        $parts = parse_url($url);

        // Reject anything pointing at a different host (open-redirect guard).
        if (isset($parts['host']) && $parts['host'] !== request()->getHost()) {
            return false;
        }

        $path = $parts['path'] ?? '/';
        foreach (['/login', '/register-clinic', '/logout'] as $authPath) {
            if (str_starts_with($path, $authPath)) {
                return false;
            }
        }

        return true;
    }

    public function sendOtp(Request $request, OtpDispatcher $dispatcher)
    {
        $request->validate([
            'phone' => 'required|string|regex:/^05\d{8}$/',
        ], [
            'phone.regex' => __('site.phone_invalid'),
        ]);

        $phone = $request->phone;

        $existing = User::where('phone', $phone)->first();
        if ($existing && ! $existing->is_active) {
            return $this->fail($request, 'phone', __('site.account_blocked'));
        }

        // Per-phone send guard: stops a number from being SMS-bombed even
        // across rotating IPs (route throttle only covers per-IP).
        if ($wait = OtpCode::throttleSend($phone, 'login')) {
            return $this->fail($request, 'phone', __('site.otp_too_many', ['seconds' => $wait]), ['retry_after' => $wait]);
        }

        $otp = OtpCode::generate($phone);

        // Deliver over the admin-selected channel (WhatsApp / SMS) with
        // automatic fallback. Each transport logs instead of sending in local
        // / when unconfigured, so dev never blocks.
        $dispatcher->send($phone, $otp->code);

        // In development, also surface the code so testers can log in without SMS/WhatsApp.
        $devCode = app()->isLocal() ? $otp->code : null;

        if ($request->wantsJson()) {
            return response()->json([
                'ok'       => true,
                'message'  => __('site.otp_sent_to', ['phone' => $phone]),
                'dev_code' => $devCode,
                'cooldown' => 60, // mirrors the 60s between-sends guard in OtpCode::throttleSend
            ]);
        }

        // Non-JS fallback: original flash-based two-step page.
        return back()->with('otp_sent', true)->with('dev_code', $devCode)->withInput();
    }

    public function verifyOtp(Request $request)
    {
        // A duplicate submit can land right after the first one already
        // logged the user in (the session was migrated and the OTP marked
        // used). Don't reject the second request — just forward the
        // now-authenticated user to where they were headed.
        if (auth('web')->check()) {
            return redirect()->intended(route('home'));
        }

        $request->validate([
            'phone' => 'required|string|max:20',
            'code' => 'required|string|size:6',
        ]);

        // Fetch the latest live code for this phone WITHOUT matching on the
        // entered digits — we need the record itself to count wrong tries.
        $otp = OtpCode::where('phone', $request->phone)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        // No active code (never sent, expired, or already burned by too many
        // wrong tries) → the user must request a fresh one.
        if (! $otp) {
            return $this->fail($request, 'code', __('site.otp_expired_request_new'), ['must_resend' => true]);
        }

        // Wrong code: count the try and either tell them how many remain or,
        // once the cap is hit, burn the code so they must resend. The client
        // stays on the OTP step throughout — it never bounces back to phone entry.
        if ($otp->code !== $request->code) {
            $remaining = $otp->registerFailedAttempt();

            $message = $remaining > 0
                ? __('site.otp_invalid_attempts', ['count' => $remaining])
                : __('site.otp_too_many_attempts');

            return $this->fail($request, 'code', $message, [
                'remaining'   => $remaining,
                'must_resend' => $remaining === 0,
            ]);
        }

        $otp->update(['is_used' => true]);

        $user = User::where('phone', $request->phone)->first();

        // Returning user → straight in.
        if ($user) {
            if (! $user->is_active) {
                return $this->fail($request, 'phone', __('site.account_blocked'));
            }

            return $this->completeLogin($request, $user);
        }

        // New user (JS flow): defer account creation until they give a name.
        // Remember that THIS phone passed OTP so completeProfile() can trust it
        // without re-verifying — it lives server-side, the client can't forge it.
        if ($request->wantsJson()) {
            $request->session()->put('otp_verified_phone', $request->phone);

            return response()->json(['ok' => true, 'needs_name' => true, 'redirect' => null]);
        }

        // Non-JS fallback: the name step can't be driven without JS, so create
        // the account with a placeholder and let the user edit it later.
        $user = User::create([
            'phone'     => $request->phone,
            'name'      => __('admin.widgets.default_user_name'),
            'is_active' => true,
        ]);

        return $this->completeLogin($request, $user);
    }

    /**
     * Second leg of new-user signup: the name is mandatory, and the account is
     * only created now (so we never persist half-formed, nameless users). The
     * caller is authorized solely by the OTP-verified phone stashed in the
     * session by {@see verifyOtp()} — no separate auth needed.
     */
    public function completeProfile(Request $request)
    {
        $phone = $request->session()->get('otp_verified_phone');

        // No verified phone → they never passed OTP (or the session expired).
        if (! $phone) {
            return $this->fail($request, 'name', __('site.otp_expired_request_new'), ['must_restart' => true]);
        }

        $request->validate([
            'name' => 'required|string|min:2|max:60',
        ], [
            'name.required' => __('site.name_required'),
            'name.min'      => __('site.name_required'),
        ]);

        // firstOrCreate guards against a double-submit racing two accounts.
        $user = User::firstOrCreate(
            ['phone' => $phone],
            ['name' => trim($request->input('name')), 'is_active' => true]
        );
        if (blank($user->name)) {
            $user->update(['name' => trim($request->input('name'))]);
        }

        $request->session()->forget('otp_verified_phone');

        if (! $user->is_active) {
            return $this->fail($request, 'phone', __('site.account_blocked'));
        }

        return $this->completeLogin($request, $user);
    }

    /**
     * Log the user in, feed the activity timeline, and hand back the return-to
     * URL — as JSON for the AJAX flow or a redirect for the form fallback.
     */
    private function completeLogin(Request $request, User $user)
    {
        auth('web')->login($user, remember: true);

        // A guest who tapped "Follow" on a complex is finishing login now —
        // apply the stashed follow so the action they initiated completes.
        if ($clinicId = session()->pull('pending_follow')) {
            $user->following()->syncWithoutDetaching([$clinicId]);
        }

        // Feed the super-admin "comprehensive user profile" timeline.
        $tracker = app(UserActivityLogger::class);
        $tracker->touchVisitSession($request, $user->id);
        $tracker->logOtpVerified($request, $user->id, 'login');
        $tracker->logLogin($request, $user->id);

        // Pull the stored return-to (set in showLogin) for both transports so
        // the JSON client and the form fallback land on the same page.
        $target = $request->session()->pull('url.intended', route('home'));

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'redirect' => $target]);
        }

        return redirect()->to($target);
    }

    /**
     * Uniform failure response: a 422 JSON payload for the AJAX login flow,
     * or the classic redirect-back-with-errors for the non-JS fallback. The
     * $extra keys (remaining / must_resend / retry_after) let the client
     * decide whether to keep the user on the OTP step or re-enable resend.
     */
    private function fail(Request $request, string $field, string $message, array $extra = [])
    {
        if ($request->wantsJson()) {
            return response()->json(array_merge(['ok' => false, 'message' => $message], $extra), 422);
        }

        return back()->withErrors([$field => $message])->withInput();
    }

    public function logout(Request $request)
    {
        $userId = auth('web')->id();
        auth('web')->logout();
        if ($userId) {
            $tracker = app(UserActivityLogger::class);
            $tracker->logLogout($request, $userId);
            $tracker->closeVisitSession($userId);
        }
        return redirect()->route('home');
    }
}
