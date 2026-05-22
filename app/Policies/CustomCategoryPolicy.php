<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\CustomCategory;

class CustomCategoryPolicy
{
    public function viewAny(Clinic $clinic): bool
    {
        return true;
    }

    public function view(Clinic $clinic, CustomCategory $category): bool
    {
        return $clinic->id === $category->clinic_id;
    }

    public function create(Clinic $clinic): bool
    {
        return true;
    }

    public function update(Clinic $clinic, CustomCategory $category): bool
    {
        return $clinic->id === $category->clinic_id;
    }

    public function delete(Clinic $clinic, CustomCategory $category): bool
    {
        // Mirror of CustomCategoryResource::canDelete — only when no services attached.
        return $clinic->id === $category->clinic_id && $category->services()->count() === 0;
    }

    public function reorder(Clinic $clinic): bool
    {
        return true;
    }
}
