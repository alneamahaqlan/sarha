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
    public function showLogin()
    {
        return view('auth.login');
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
