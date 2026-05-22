<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\AuditLog;

class AuditLogPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function view(Admin $admin, AuditLog $log): bool
    {
        return $admin->is_active;
    }

    // Mirrors Filament canCreate/canEdit/canDelete = false.
    public function create(Admin $admin): bool { return false; }
    public function update(Admin $admin, AuditLog $log): bool { return false; }
    public function delete(Admin $admin, AuditLog $log): bool { return false; }
}
