<?php
use App\Models\Customer;

$c = Customer::query()->first();
if (! $c) { echo "NO CUSTOMER\n"; return; }
echo "Customer #{$c->id} priority(before)={$c->follow_up_priority}\n";

$c->update(['follow_up_priority' => 3]);
echo "after update => {$c->fresh()->follow_up_priority}\n";

$clinicId = $c->clinic_id;
$filtered = Customer::where('clinic_id', $clinicId)->where('follow_up_priority', 3)->count();
echo "clinic {$clinicId} with priority=3 => {$filtered}\n";

$sorted = Customer::where('clinic_id', $clinicId)
    ->orderByDesc('follow_up_priority')->orderByDesc('last_interaction_at')
    ->limit(3)->pluck('follow_up_priority')->all();
echo "top-3 by priority desc => " . json_encode($sorted) . "\n";

// reset
$c->update(['follow_up_priority' => 0]);
echo "reset => {$c->fresh()->follow_up_priority}\nOK\n";
