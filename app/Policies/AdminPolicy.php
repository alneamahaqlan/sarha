<?php

namespace App\Policies;

use App\Models\Admin;

class AdminPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function view(Admin $admin, Admin $target): bool
    {
        return $admin->is_active;
    }

    public function create(Admin $admin): bool
    {
        // Filament resource is reachable by every active admin; only super_admin should create.
        return $admin->is_active && $admin->isSuperAdmin();
    }

    public function update(Admin $admin, Admin $target): bool
    {
        if (! $admin->is_active) {
            return false;
        }

        // Anyone active can update their own row; only super_admin can update others.
        return $admin->id === $target->id || $admin->isSuperAdmin();
    }

    public function delete(Admin $admin, Admin $target): bool
    {
        // Cannot delete self; only super_admin can delete others.
        if (! $admin->is_active || $admin->id === $target->id) {
            return false;
        }

        return $admin->isSuperAdmin();
    }
}
