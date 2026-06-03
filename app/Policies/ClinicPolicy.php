<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Clinic;

class ClinicPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function view(Admin $admin, Clinic $clinic): bool
    {
        return $admin->is_active;
    }

    public function create(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function update(Admin $admin, Clinic $clinic): bool
    {
        return $admin->is_active;
    }

    public function delete(Admin $admin, Clinic $clinic): bool
    {
        return $admin->is_active;
    }

    public function restore(Admin $admin, Clinic $clinic): bool
    {
        return $admin->is_active;
    }

    public function forceDelete(Admin $admin, Clinic $clinic): bool
    {
        return $admin->is_active && $admin->isSuperAdmin();
    }

    // ----- Custom action abilities mirroring Filament visible() rules. -----

    public function approve(Admin $admin, Clinic $clinic): bool
    {
        return $admin->is_active && $clinic->status === 'pending';
    }

    public function reject(Admin $admin, Clinic $clinic): bool
    {
        return $admin->is_active && $clinic->status === 'pending';
    }

    public function activate(Admin $admin, Clinic $clinic): bool
    {
        return $admin->is_active && in_array($clinic->status, ['suspended', 'rejected'], true);
    }

    public function suspend(Admin $admin, Clinic $clinic): bool
    {
        return $admin->is_active && $clinic->status === 'active';
    }

    public function extend(Admin $admin, Clinic $clinic): bool
    {
        return $admin->is_active && $clinic->status === 'active';
    }

    public function impersonate(Admin $admin, Clinic $clinic): bool
    {
        return $admin->is_active && $clinic->status === 'active';
    }

    /**
     * Reveal / regenerate the clinic's login password. Restricted to
     * super-admins — it exposes a real credential, same bar as rotating an
     * encrypted API key in SystemSettingPolicy.
     */
    public function viewPassword(Admin $admin, Clinic $clinic): bool
    {
        return $admin->is_active && $admin->isSuperAdmin();
    }
}
