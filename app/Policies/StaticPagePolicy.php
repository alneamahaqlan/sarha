<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\StaticPage;

class StaticPagePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function view(Admin $admin, StaticPage $page): bool
    {
        return $admin->is_active;
    }

    public function create(Admin $admin): bool
    {
        return $admin->is_active && $admin->isSuperAdmin();
    }

    public function update(Admin $admin, StaticPage $page): bool
    {
        return $admin->is_active && $admin->isSuperAdmin();
    }

    public function delete(Admin $admin, StaticPage $page): bool
    {
        // Core platform pages (about/privacy/terms/...) are protected.
        return $admin->is_active && $admin->isSuperAdmin() && ! $page->is_system;
    }

    public function reorder(Admin $admin): bool
    {
        return $admin->is_active && $admin->isSuperAdmin();
    }
}
