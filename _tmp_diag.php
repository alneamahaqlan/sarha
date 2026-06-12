<?php
use App\Models\Booking;
use App\Models\Customer;

$clinicId = 1;
$total = Booking::where('clinic_id', $clinicId)->count();
$withCust = Booking::where('clinic_id', $clinicId)->whereNotNull('customer_id')->count();
echo "clinic {$clinicId}: bookings total={$total}, with customer_id={$withCust}\n";

$priCustomers = Customer::where('clinic_id', $clinicId)->where('follow_up_priority', '>', 0)->count();
echo "customers with priority>0: {$priCustomers}\n";

// bookings whose customer has priority>0
$bk = Booking::where('clinic_id', $clinicId)
    ->whereHas('customer', fn($q) => $q->where('follow_up_priority', '>', 0))
    ->count();
echo "bookings whose customer priority>0: {$bk}\n";

// sample: show a few bookings + their customer priority
foreach (Booking::where('clinic_id', $clinicId)->with('customer:id,follow_up_priority')->limit(5)->get() as $b) {
    $p = $b->customer?->follow_up_priority;
    echo "  booking #{$b->id} customer_id=" . ($b->customer_id ?? 'NULL') . " priority=" . ($p ?? 'n/a') . "\n";
}
echo "OK\n";
