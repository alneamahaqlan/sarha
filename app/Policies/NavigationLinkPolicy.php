<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\NavigationLink;

class NavigationLinkPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function view(Admin $admin, NavigationLink $link): bool
    {
        return $admin->is_active;
    }

    public function create(Admin $admin): bool
    {
        return $admin->is_active && $admin->isSuperAdmin();
    }

    public function update(Admin $admin, NavigationLink $link): bool
    {
        return $admin->is_active && $admin->isSuperAdmin();
    }

    public function delete(Admin $admin, NavigationLink $link): bool
    {
        return $admin->is_active && $admin->isSuperAdmin();
    }

    public function reorder(Admin $admin): bool
    {
        return $admin->is_active && $admin->isSuperAdmin();
    }
}
