<?php

namespace App\Http\Controllers\Api\V1\Shared;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Clinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'guard'    => 'required|in:admin,clinic',
            'email'    => 'required_if:guard,admin|nullable|string|email',
            'phone'    => 'required_if:guard,clinic|nullable|string',
            'password' => 'required|string',
        ]);

        $guard = $data['guard'];

        if ($guard === 'admin') {
            $admin = Admin::where('email', $data['email'])->first();
            if (! $admin || ! Hash::check($data['password'], $admin->password)) {
                throw ValidationException::withMessages(['email' => __('auth.failed')]);
            }
            if (! $admin->is_active) {
                throw ValidationException::withMessages(['email' => __('auth.failed')]);
            }
            Auth::guard('admin')->login($admin, remember: true);
        } else {
            $clinic = Clinic::where('phone', $data['phone'])->first();
            if (! $clinic || ! Hash::check($data['password'], $clinic->password)) {
                throw ValidationException::withMessages(['phone' => __('auth.failed')]);
            }
            Auth::guard('clinic')->login($clinic, remember: true);
        }

        $request->session()->regenerate();

        return response()->json(['data' => $this->currentUserPayload($guard)]);
    }

    public function logout(Request $request): JsonResponse
    {
        foreach (['admin', 'clinic', 'web'] as $g) {
            if (Auth::guard($g)->check()) {
                Auth::guard($g)->logout();
            }
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        foreach (['admin', 'clinic', 'web'] as $g) {
            if (Auth::guard($g)->check()) {
                return response()->json(['data' => $this->currentUserPayload($g)]);
            }
        }

        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    private function currentUserPayload(string $guard): array
    {
        $user = Auth::guard($guard)->user();

        $payload = [
            'guard' => $guard,
            'user'  => [
                'id'    => $user->getKey(),
                'name'  => $user->name ?? null,
                'email' => $user->email ?? null,
                'phone' => $user->phone ?? null,
                'role'  => $user->role ?? null,
            ],
            'permissions' => $this->permissionMap($guard, $user),
            'impersonating' => session()->has(\App\Services\ImpersonationService::SESSION_KEY),
        ];

        return $payload;
    }

    private function permissionMap(string $guard, $user): array
    {
        if ($guard === 'admin') {
            $isSuper = method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            return [
                'clinics.viewAny'      => true,
                'clinics.create'       => $isSuper,
                'clinics.update'       => true,
                'clinics.delete'       => $isSuper,
                'clinics.restore'      => $isSuper,
                'clinics.forceDelete'  => $isSuper,
                'clinics.approve'      => true,
                'clinics.reject'       => true,
                'clinics.impersonate'  => true,
                'clinics.extend'       => true,
                'users.viewAny'        => true,
                'cities.viewAny'       => true,
                'cities.create'        => true,
                'cities.update'        => true,
                'cities.delete'        => true,
                'categories.viewAny'   => true,
                'categories.create'    => true,
                'categories.update'    => true,
                'categories.delete'    => true,
                'bookings.viewAny'     => true,
                'complaints.viewAny'   => true,
                'complaints.update'    => true,
                'sales_leads.viewAny'  => true,
                'sales_leads.create'   => true,
                'sales_leads.update'   => true,
                'sales_leads.delete'   => true,
                'sales_leads.convert'  => true,
                'subscriptions.viewAny'=> true,
                'subscriptions.create' => true,
                'subscriptions.update' => true,
                'complaints.create'    => true,
                'audit_logs.viewAny'   => true,
                'system_settings.update' => true,
                'mass_notify.send'     => true,
                'articles.viewAny'     => true,
                'articles.create'      => true,
                'articles.update'      => true,
                'articles.delete'      => true,
            ];
        }

        if ($guard === 'clinic') {
            return [
                'services.viewAny'         => true,
                'services.create'          => true,
                'services.update'          => true,
                'services.delete'          => true,
                'bookings.viewAny'         => true,
                'bookings.update'          => true,
                'articles.viewAny'         => true,
                'articles.create'          => true,
                'articles.update'          => true,
                'articles.delete'          => true,
                'custom_categories.viewAny'=> true,
                'custom_categories.create' => true,
                'custom_categories.update' => true,
                'custom_categories.delete' => true,
                'price_quotes.viewAny'     => true,
                'price_quotes.update'      => true,
                'profile.update'           => true,
                'import_services.execute'  => true,
            ];
        }

        return [];
    }
}
