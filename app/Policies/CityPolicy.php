<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\City;

class CityPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function view(Admin $admin, City $city): bool
    {
        return $admin->is_active;
    }

    public function create(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function update(Admin $admin, City $city): bool
    {
        return $admin->is_active;
    }

    public function delete(Admin $admin, City $city): bool
    {
        if (! $admin->is_active) {
            return false;
        }

        // Mirror of CityResource::canDelete — Filament behavior preserved exactly.
        return $city->clinics()->count() === 0;
    }
}
