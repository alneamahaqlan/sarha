<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Complaint;

class ComplaintPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function view(Admin $admin, Complaint $complaint): bool
    {
        return $admin->is_active;
    }

    public function create(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function update(Admin $admin, Complaint $complaint): bool
    {
        return $admin->is_active;
    }

    public function delete(Admin $admin, Complaint $complaint): bool
    {
        return $admin->is_active && $admin->isSuperAdmin();
    }

    public function markInReview(Admin $admin, Complaint $complaint): bool
    {
        // Mirrors Filament visible() — only meaningful when status is 'new'.
        return $admin->is_active && $complaint->status === 'new';
    }

    public function resolve(Admin $admin, Complaint $complaint): bool
    {
        return $admin->is_active && in_array($complaint->status, ['new', 'in_review'], true);
    }

    public function reject(Admin $admin, Complaint $complaint): bool
    {
        return $admin->is_active && in_array($complaint->status, ['new', 'in_review'], true);
    }

    public function notifyClinic(Admin $admin, Complaint $complaint): bool
    {
        return $admin->is_active && $complaint->clinic_id && ! $complaint->clinic_notified;
    }
}
