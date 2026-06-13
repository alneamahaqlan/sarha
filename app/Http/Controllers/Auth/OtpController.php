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
            return back()->withErrors(['phone' => __('site.account_blocked')])->withInput();
        }

        // Per-phone send guard: stops a number from being SMS-bombed even
        // across rotating IPs (route throttle only covers per-IP).
        if ($wait = OtpCode::throttleSend($phone, 'login')) {
            return back()
                ->withErrors(['phone' => __('site.otp_too_many', ['seconds' => $wait])])
                ->withInput();
        }

        $otp = OtpCode::generate($phone);

        // Deliver over the admin-selected channel (WhatsApp / SMS) with
        // automatic fallback. Each transport logs instead of sending in local
        // / when unconfigured, so dev never blocks.
        $dispatcher->send($phone, $otp->code);

        // In development, also flash the code so testers can log in without SMS/WhatsApp.
        if (app()->isLocal()) {
            return back()->with('otp_sent', true)->with('dev_code', $otp->code)->withInput();
        }

        return back()->with('otp_sent', true)->withInput();
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

        $otp = OtpCode::where('phone', $request->phone)
            ->where('code', $request->code)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otp) {
            return back()->withErrors(['code' => __('site.otp_invalid')])->withInput();
        }

        $otp->update(['is_used' => true]);

        $user = User::firstOrCreate(
            ['phone' => $request->phone],
            ['name' => __('admin.widgets.default_user_name'), 'is_active' => true]
        );

        if (! $user->is_active) {
            return back()->withErrors(['phone' => __('site.account_blocked')])->withInput();
        }

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

        return redirect()->intended(route('home'));
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
