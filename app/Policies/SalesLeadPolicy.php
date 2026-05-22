<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\SalesLead;

class SalesLeadPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function view(Admin $admin, SalesLead $lead): bool
    {
        return $admin->is_active;
    }

    public function create(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function update(Admin $admin, SalesLead $lead): bool
    {
        return $admin->is_active;
    }

    public function delete(Admin $admin, SalesLead $lead): bool
    {
        return $admin->is_active && $admin->isSuperAdmin();
    }

    public function convert(Admin $admin, SalesLead $lead): bool
    {
        // Mirrors Filament visible() — cannot convert an already-converted lead.
        return $admin->is_active && $lead->status !== 'converted';
    }
}
