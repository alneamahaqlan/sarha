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
        // Any active admin may delete a complaint (was super-admin only).
        return $admin->is_active;
    }

    public function replyToCustomer(Admin $admin, Complaint $complaint): bool
    {
        // Reply only makes sense for complaints that came from a customer account.
        return $admin->is_active && $complaint->source === 'customer' && $complaint->user_id;
    }

    public function reopen(Admin $admin, Complaint $complaint): bool
    {
        return $admin->is_active && in_array($complaint->status, ['resolved', 'rejected'], true);
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
