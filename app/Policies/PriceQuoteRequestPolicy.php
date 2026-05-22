<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Clinic;
use App\Models\PriceQuoteRequest;

class PriceQuoteRequestPolicy
{
    public function viewAny(Admin|Clinic $actor): bool
    {
        if ($actor instanceof Admin) {
            return $actor->is_active;
        }
        return true;
    }

    public function view(Admin|Clinic $actor, PriceQuoteRequest $quote): bool
    {
        if ($actor instanceof Admin) {
            return $actor->is_active;
        }
        return $actor->id === $quote->clinic_id;
    }

    public function create(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function update(Admin|Clinic $actor, PriceQuoteRequest $quote): bool
    {
        if ($actor instanceof Admin) {
            return $actor->is_active;
        }
        return $actor->id === $quote->clinic_id;
    }

    public function delete(Admin $admin, PriceQuoteRequest $quote): bool
    {
        return $admin->is_active;
    }
}
