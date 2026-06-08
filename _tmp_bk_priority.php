<?php
use App\Models\Booking;
use App\Models\Customer;
use App\Http\Resources\Api\V1\BookingKanbanCardResource;

$booking = Booking::whereNotNull('customer_id')->with('customer:id,follow_up_priority')->first();
if (! $booking) { echo "NO BOOKING WITH CUSTOMER\n"; return; }

$cust = $booking->customer;
echo "Booking #{$booking->id} customer #{$cust->id} priority={$cust->follow_up_priority}\n";

$cust->update(['follow_up_priority' => 2]);
$booking->load('customer:id,follow_up_priority');
$arr = (new BookingKanbanCardResource($booking))->toArray(request());
echo "card.follow_up_priority => " . ($arr['follow_up_priority'] ?? 'MISSING') . "\n";

$cust->update(['follow_up_priority' => 0]);
echo "reset OK\n";
