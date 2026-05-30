<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\Customer;
use App\Services\CustomerLinker;

/**
 * Stamps each new Booking with its owning Customer (creating /
 * upserting the Customer if needed) and keeps the Customer's
 * counters in sync as the booking moves through states.
 *
 * Lives in addition to (not instead of) the notification BookingObserver
 * — Laravel happily fires every registered observer.
 */
class BookingCustomerLinkObserver
{
    public function __construct(private readonly CustomerLinker $linker) {}

    public function creating(Booking $booking): void
    {
        if ($booking->customer_id) return; // explicit caller wins
        $customer = $this->linker->forBooking($booking);
        if ($customer) {
            $booking->customer_id = $customer->id;
        }
    }

    public function created(Booking $booking): void
    {
        if (! $booking->customer_id) return;
        $customer = Customer::find($booking->customer_id);
        if (! $customer) return;

        $this->linker->recordInteraction($customer, Customer::TYPE_BOOKING, $booking->created_at);

        // If the booking was created already in 'completed' (rare —
        // direct seeding or admin backfill) reflect it immediately.
        if ($booking->status === Booking::STATUS_COMPLETED) {
            $this->linker->markBookingCompleted($customer);
        }
    }

    public function updated(Booking $booking): void
    {
        if (! $booking->customer_id) return;
        if (! $booking->wasChanged('status')) return;

        // Only count the new→completed transition. Re-opening a
        // completed booking (uncommon) doesn't decrement; we accept
        // that drift as the lesser evil over add/subtract churn.
        if ($booking->status === Booking::STATUS_COMPLETED
            && $booking->getOriginal('status') !== Booking::STATUS_COMPLETED) {
            $customer = Customer::find($booking->customer_id);
            if ($customer) {
                $this->linker->markBookingCompleted($customer);
            }
        }
    }
}
