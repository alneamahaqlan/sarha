<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Clinic;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Lightweight session-based impersonation.
 * No external package — keeps it auditable + simple.
 *
 * Flow:
 *   1) Admin clicks "Log in as complex" on a Clinic row
 *   2) start() saves the admin id in session, logs admin out of admin guard,
 *      logs in as the clinic on the clinic guard
 *   3) A red banner shows on every clinic-panel page while impersonating
 *   4) stop() reverses it: logs out clinic, logs admin back in
 */
class ImpersonationService
{
    public const SESSION_KEY = 'impersonator_admin_id';

    public function start(Admin $admin, Clinic $clinic): void
    {
        Session::put(self::SESSION_KEY, $admin->id);

        Auth::guard('admin')->logout();
        Auth::guard('clinic')->login($clinic);

        AuditLogService::log('impersonation_started', $clinic, [
            'admin_id' => $admin->id,
        ]);

        app(NotificationService::class)->adminImpersonated($clinic, $admin);
    }

    public function stop(): ?Admin
    {
        $adminId = Session::pull(self::SESSION_KEY);
        if (! $adminId) return null;

        $admin = Admin::find($adminId);
        if (! $admin) return null;

        Auth::guard('clinic')->logout();
        Auth::guard('admin')->login($admin);

        AuditLogService::log('impersonation_ended', $admin, []);

        return $admin;
    }

    public function isImpersonating(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    public function originalAdmin(): ?Admin
    {
        $id = Session::get(self::SESSION_KEY);
        return $id ? Admin::find($id) : null;
    }
}
