<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\ServiceCategory;

class ServiceCategoryPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function view(Admin $admin, ServiceCategory $serviceCategory): bool
    {
        return $admin->is_active;
    }

    public function create(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function update(Admin $admin, ServiceCategory $serviceCategory): bool
    {
        return $admin->is_active;
    }

    public function delete(Admin $admin, ServiceCategory $serviceCategory): bool
    {
        // Mirror of ServiceCategoryController::destroy guard — never delete
        // a category that still classifies live services.
        return $admin->is_active && $serviceCategory->services()->doesntExist();
    }

    public function reorder(Admin $admin): bool
    {
        return $admin->is_active;
    }
}
