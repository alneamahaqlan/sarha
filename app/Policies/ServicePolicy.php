<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Clinic;
use App\Models\Service;

class ServicePolicy
{
    public function viewAny(Admin|Clinic $actor): bool
    {
        if ($actor instanceof Admin) {
            return $actor->is_active;
        }
        return true;
    }

    public function view(Admin|Clinic $actor, Service $service): bool
    {
        if ($actor instanceof Admin) {
            return $actor->is_active;
        }
        return $actor->id === $service->clinic_id;
    }

    public function create(Admin|Clinic $actor): bool
    {
        if ($actor instanceof Admin) {
            return $actor->is_active;
        }
        return true;
    }

    public function update(Admin|Clinic $actor, Service $service): bool
    {
        if ($actor instanceof Admin) {
            return $actor->is_active;
        }
        return $actor->id === $service->clinic_id;
    }

    public function delete(Admin|Clinic $actor, Service $service): bool
    {
        if ($actor instanceof Admin) {
            return $actor->is_active;
        }
        return $actor->id === $service->clinic_id;
    }
}
