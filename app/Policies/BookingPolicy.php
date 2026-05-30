<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Clinic;

class BookingPolicy
{
    public function viewAny(Admin|Clinic $actor): bool
    {
        if ($actor instanceof Admin) {
            return $actor->is_active;
        }
        return true;
    }

    public function view(Admin|Clinic $actor, Booking $booking): bool
    {
        if ($actor instanceof Admin) {
            return $actor->is_active;
        }
        return $actor->id === $booking->clinic_id;
    }

    public function create(Admin|Clinic $actor): bool
    {
        if ($actor instanceof Admin) {
            return $actor->is_active;
        }
        // Walk-in / phone bookings entered by the clinic team via the
        // Kanban "إضافة حجز" CTA. Per-role gating lives on the route
        // (clinic.role:bookings.*).
        return true;
    }

    public function update(Admin|Clinic $actor, Booking $booking): bool
    {
        if ($actor instanceof Admin) {
            return $actor->is_active;
        }
        return $actor->id === $booking->clinic_id;
    }

    public function delete(Admin $admin, Booking $booking): bool
    {
        // Only admins delete (matches Filament Resource — clinic panel has no delete).
        return $admin->is_active;
    }

    public function restore(Admin $admin, Booking $booking): bool
    {
        return $admin->is_active;
    }

    public function forceDelete(Admin $admin, Booking $booking): bool
    {
        return $admin->is_active && $admin->isSuperAdmin();
    }
}
