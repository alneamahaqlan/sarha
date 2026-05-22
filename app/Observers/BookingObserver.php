<?php

namespace App\Observers;

use App\Models\Booking;
use App\Services\NotificationService;

class BookingObserver
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function created(Booking $booking): void
    {
        // Admins get a quiet log entry
        $this->notifications->newBooking($booking);

        // The clinic owner gets a high-priority "new booking" toast
        $this->notifications->newClinicBooking($booking);
    }
}
