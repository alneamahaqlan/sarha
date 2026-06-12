<?php

use App\Models\Clinic;
use App\Models\CustomerReminder;
use App\Models\PlatformNotification;
use Illuminate\Support\Facades\Artisan;

$clinic = Clinic::first();
if (! $clinic) { echo "NO CLINIC\n"; return; }

// General task: no customer, has a title.
$task = CustomerReminder::create([
    'clinic_id'   => $clinic->id,
    'customer_id' => null,
    'title'       => 'تجهيز تقرير الأداء الشهري',
    'remind_at'   => now()->subMinutes(2),
    'status'      => 'pending',
    'created_by_type' => Clinic::class,
    'created_by_id'   => $clinic->id,
    'created_by_name' => $clinic->name,
]);
echo "Created general task #{$task->id} (customer_id=" . var_export($task->customer_id, true) . ", title='{$task->title}')\n";

$beforeTask = PlatformNotification::where('event_type', 'task_reminder_due')->count();
Artisan::call('saerha:dispatch-contact-reminders');
$afterTask = PlatformNotification::where('event_type', 'task_reminder_due')->count();

$note = PlatformNotification::where('event_type', 'task_reminder_due')->latest('id')->first();
echo "[Task notif] before={$beforeTask} after={$afterTask}\n";
echo "[Task notif] title: " . ($note?->title ?? 'NONE') . "\n";
echo "[Task notif] body:  " . ($note?->body ?? 'NONE') . "\n";
echo "[Task notif] url:   " . ($note?->url ?? 'NONE') . "\n";

$task->refresh();
echo "[Idempotency] task.notified_at = " . ($task->notified_at?->toIso8601String() ?? 'NULL') . "\n";

// cleanup
$task->delete();
if ($note) $note->delete();
echo "Cleaned up.\n";
