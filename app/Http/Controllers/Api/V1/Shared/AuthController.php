<?php

namespace App\Http\Controllers\Api\V1\Shared;

use App\Enums\ClinicRole;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Clinic;
use App\Models\ClinicTeamMember;
use App\Services\ClinicActivityLogger;
use App\Support\ActingClinicUser;
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
            $this->loginClinic($data['phone'], $data['password']);
        }

        $request->session()->regenerate();

        // Activity log: clinic logins (both owner + member) only —
        // admin sessions are tracked separately via AuditLogService.
        if ($guard === 'clinic') {
            app(ClinicActivityLogger::class)->log('auth.login');
        }

        return response()->json(['data' => $this->currentUserPayload($guard)]);
    }

    /**
     * Two-stage clinic login: try the Clinic owner row first (existing
     * behaviour), fall back to a team-member row keyed by the same
     * phone. Members are logged in as their owning Clinic via the
     * `clinic` guard, then the session is stamped with the member id
     * so ActingClinicUser can resolve the acting identity.
     */
    private function loginClinic(string $phone, string $password): void
    {
        // 1. Owner-as-clinic path (unchanged).
        $clinic = Clinic::where('phone', $phone)->first();
        if ($clinic && Hash::check($password, $clinic->password)) {
            Auth::guard('clinic')->login($clinic, remember: true);
            ActingClinicUser::clearActingMember();
            return;
        }

        // 2. Team-member path. Only active, non-soft-deleted members
        // can authenticate; their owning clinic must also be active.
        $member = ClinicTeamMember::active()
            ->where('phone', $phone)
            ->first();
        if (! $member || ! Hash::check($password, $member->password)) {
            throw ValidationException::withMessages(['phone' => __('auth.failed')]);
        }

        $owner = $member->clinic;
        if (! $owner || $owner->status !== 'active') {
            throw ValidationException::withMessages(['phone' => __('auth.failed')]);
        }

        Auth::guard('clinic')->login($owner, remember: true);
        ActingClinicUser::setActingMember($member);
        $member->forceFill(['last_login_at' => now()])->saveQuietly();
    }

    public function logout(Request $request): JsonResponse
    {
        // Capture activity BEFORE we tear down the session — the
        // logger reads the actor from there.
        if (Auth::guard('clinic')->check()) {
            app(ClinicActivityLogger::class)->log('auth.logout');
        }

        foreach (['admin', 'clinic', 'web'] as $g) {
            if (Auth::guard($g)->check()) {
                Auth::guard($g)->logout();
            }
        }
        ActingClinicUser::clearActingMember();
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
                'slug'  => $guard === 'clinic' ? ($user->slug ?? null) : null,
            ],
            'permissions' => $this->permissionMap($guard, $user),
            'impersonating' => session()->has(\App\Services\ImpersonationService::SESSION_KEY),
        ];

        // Acting overlay — surfaces the team-member identity to the
        // React layer so the header can show the member's name + role
        // badge without breaking any caller that reads `user.id` /
        // `user.name` (which still describe the clinic).
        if ($guard === 'clinic') {
            $payload['acting'] = $this->actingPayload();
        }

        return $payload;
    }

    /**
     * Describes WHO is currently using the clinic panel: the owner
     * (Clinic itself) or a team member acting on its behalf.
     */
    private function actingPayload(): array
    {
        $actor = ActingClinicUser::actor();
        $role = ActingClinicUser::role();

        return [
            'type'  => $actor instanceof ClinicTeamMember ? 'member' : 'owner',
            'id'    => $actor?->getKey(),
            'name'  => ActingClinicUser::actorName(),
            'role'  => $role->value,
            'is_owner' => $actor instanceof Clinic,
        ];
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
            // Role-based map driven by the ClinicRole enum so the
            // sidebar filter + route middleware + admin badge all
            // read from one source. Owner gets `*`, members get the
            // pattern list defined in the enum.
            $role = ActingClinicUser::role();
            $payload = $role->permissionMap();

            // Legacy keys still consumed by older clinic-side code
            // paths. Mapped from the new ability set so behaviour is
            // unchanged for owner sessions; restricted for members.
            $payload['services.viewAny']         = $role->can('services.view');
            $payload['services.create']          = $role->can('services.manage');
            $payload['services.update']          = $role->can('services.manage');
            $payload['services.delete']          = $role->can('services.manage');
            $payload['bookings.viewAny']         = $role->can('bookings.view');
            $payload['bookings.update']          = $role->can('bookings.update');
            $payload['articles.viewAny']         = $role->can('articles.view');
            $payload['articles.create']          = $role->can('articles.manage');
            $payload['articles.update']          = $role->can('articles.manage');
            $payload['articles.delete']          = $role->can('articles.manage');
            $payload['custom_categories.viewAny']= $role->can('category_requests.view');
            $payload['custom_categories.create'] = $role->can('category_requests.view');
            $payload['custom_categories.update'] = $role->can('category_requests.view');
            $payload['custom_categories.delete'] = $role->can('category_requests.view');
            $payload['price_quotes.viewAny']     = $role->can('price_quotes.view');
            $payload['price_quotes.update']      = $role->can('price_quotes.reply');
            $payload['profile.update']           = $role->can('profile.manage');
            $payload['import_services.execute']  = $role->can('services.manage');

            return $payload;
        }

        return [];
    }
}
