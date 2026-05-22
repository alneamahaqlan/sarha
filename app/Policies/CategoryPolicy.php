<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Category;

class CategoryPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function view(Admin $admin, Category $category): bool
    {
        return $admin->is_active;
    }

    public function create(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function update(Admin $admin, Category $category): bool
    {
        return $admin->is_active;
    }

    public function delete(Admin $admin, Category $category): bool
    {
        if (! $admin->is_active) {
            return false;
        }

        // Mirror of CategoryResource::canDelete — Filament behavior preserved exactly.
        return $category->clinics()->count() === 0;
    }

    public function reorder(Admin $admin): bool
    {
        return $admin->is_active;
    }
}
