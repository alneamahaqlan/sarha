<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\SystemSetting;

class SystemSettingPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function view(Admin $admin, SystemSetting $setting): bool
    {
        return $admin->is_active;
    }

    public function update(Admin $admin, SystemSetting $setting): bool
    {
        return $admin->is_active;
    }

    // Mirrors Filament canCreate/canDelete = false.
    public function create(Admin $admin): bool { return false; }
    public function delete(Admin $admin, SystemSetting $setting): bool { return false; }
}
