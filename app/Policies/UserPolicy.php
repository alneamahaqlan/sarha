<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\User;

class UserPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function view(Admin $admin, User $user): bool
    {
        return $admin->is_active;
    }

    public function create(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function update(Admin $admin, User $user): bool
    {
        return $admin->is_active;
    }

    public function delete(Admin $admin, User $user): bool
    {
        // Filament UserResource has no Delete action — keep parity by disallowing.
        return false;
    }
}
