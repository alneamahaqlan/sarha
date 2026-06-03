<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\WhatsAppSender;

/**
 * WhatsApp sender numbers carry a delivery credential (the gateway token),
 * so listing is open to any active admin but creating / editing / removing a
 * number is restricted to super-admins — mirrors how SystemSettingPolicy
 * guards encrypted API-key slots.
 */
class WhatsAppSenderPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function view(Admin $admin, WhatsAppSender $sender): bool
    {
        return $admin->is_active;
    }

    public function create(Admin $admin): bool
    {
        return $admin->is_active && $admin->isSuperAdmin();
    }

    public function update(Admin $admin, WhatsAppSender $sender): bool
    {
        return $admin->is_active && $admin->isSuperAdmin();
    }

    public function delete(Admin $admin, WhatsAppSender $sender): bool
    {
        return $admin->is_active && $admin->isSuperAdmin();
    }
}
