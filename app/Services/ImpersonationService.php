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
    public const CLINIC_KEY  = 'impersonated_clinic_id';

    public function start(Admin $admin, Clinic $clinic): void
    {
        Session::put(self::SESSION_KEY, $admin->id);
        Session::put(self::CLINIC_KEY, $clinic->id);

        // Log while the admin guard is still authenticated: AuditLogService reads
        // the acting admin from the admin guard, so this MUST run before logout()
        // or the start event would be silently dropped.
        AuditLogService::log('clinic.impersonation_started', $clinic, newValues: [
            'admin_id' => $admin->id,
        ]);

        Auth::guard('admin')->logout();
        Auth::guard('clinic')->login($clinic);

        app(NotificationService::class)->adminImpersonated($clinic, $admin);
    }

    public function stop(): ?Admin
    {
        $adminId  = Session::pull(self::SESSION_KEY);
        $clinicId = Session::pull(self::CLINIC_KEY);
        if (! $adminId) return null;

        $admin = Admin::find($adminId);
        if (! $admin) return null;

        Auth::guard('clinic')->logout();
        Auth::guard('admin')->login($admin);

        // Log against the impersonated Clinic (not the Admin) so the audit trail
        // ties the session to the clinic. The acting admin is still captured via
        // the admin_id/admin_name columns on every audit row.
        $clinic = $clinicId ? Clinic::find($clinicId) : null;
        AuditLogService::log('clinic.impersonation_ended', $clinic, newValues: [
            'admin_id' => $admin->id,
        ]);

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
