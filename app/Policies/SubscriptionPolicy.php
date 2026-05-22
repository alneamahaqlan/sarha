<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Subscription;

class SubscriptionPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function view(Admin $admin, Subscription $subscription): bool
    {
        return $admin->is_active;
    }

    public function create(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function update(Admin $admin, Subscription $subscription): bool
    {
        return $admin->is_active;
    }

    public function delete(Admin $admin, Subscription $subscription): bool
    {
        // Filament has no DeleteAction on Subscription — keep parity by disallowing.
        return false;
    }
}
