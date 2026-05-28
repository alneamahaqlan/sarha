<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\HomepageSection;

/**
 * Any active admin can manage the homepage CMS. Tightening to a specific
 * role (e.g. super-admin) is a one-line change here.
 */
class HomepageSectionPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function view(Admin $admin, HomepageSection $section): bool
    {
        return $admin->is_active;
    }

    public function update(Admin $admin, HomepageSection $section = null): bool
    {
        return $admin->is_active;
    }

    public function reorder(Admin $admin): bool
    {
        return $admin->is_active;
    }
}
