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

    /**
     * Active admins can edit ordinary settings. Encrypted slots (API keys
     * for Gemini / OpenAI / Anthropic) are restricted to super-admins —
     * the key never leaves the DB in plaintext via the API, but only the
     * super-admin role can rotate or set it.
     */
    public function update(Admin $admin, SystemSetting $setting): bool
    {
        if (! $admin->is_active) return false;

        if ($setting->type === 'encrypted') {
            return $admin->isSuperAdmin();
        }

        return true;
    }

    // Mirrors Filament canCreate/canDelete = false.
    public function create(Admin $admin): bool { return false; }
    public function delete(Admin $admin, SystemSetting $setting): bool { return false; }
}
