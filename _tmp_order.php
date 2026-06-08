<?php
use App\Models\Booking;
use App\Models\Customer;
use App\Models\ClinicBookingStage;
use App\Services\BookingStageService;
use App\Services\BookingKanbanService;

$clinicId = 1;
$svc = app(BookingStageService::class);
$stages = $svc->ensureDefaults($clinicId);
$primary = $svc->primaryStageIds($stages);
$newStage = $stages->firstWhere('kind', 'new');
$isPrimary = ($primary['new'] ?? null) === $newStage->id;

$kanban = app(BookingKanbanService::class);

// Grab the first 2 bookings currently in the "new" column and bump their
// customers' priority to verify they float to the top.
$before = $kanban->columnForStage($clinicId, $newStage, $isPrimary, [], null, 20)['items'];
echo "column has " . $before->count() . " items\n";
$ids = $before->take(3)->pluck('customer_id')->all();
echo "bumping customers: " . json_encode($ids) . "\n";
if (isset($ids[0])) Customer::where('id', $ids[0])->update(['follow_up_priority' => 3]);
if (isset($ids[1])) Customer::where('id', $ids[1])->update(['follow_up_priority' => 1]);

$after = $kanban->columnForStage($clinicId, $newStage, $isPrimary, [], null, 20)['items'];
echo "order after (booking_id => priority => appt):\n";
foreach ($after->take(8) as $b) {
    $p = Customer::where('id', $b->customer_id)->value('follow_up_priority');
    echo "  #{$b->id} pri=" . ($p ?? 'NULL') . " appt=" . ($b->appointment_at ?? '—') . "\n";
}

// reset
foreach ($ids as $cid) { if ($cid) Customer::where('id', $cid)->update(['follow_up_priority' => 0]); }
echo "reset OK\n";
