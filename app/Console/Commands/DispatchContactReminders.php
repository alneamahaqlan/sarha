<?php

namespace App\Console\Commands;

use App\Enums\NotificationEvent;
use App\Models\CustomerReminder;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;

/**
 * Fires CONTACT_REMINDER_DUE for every contact reminder whose time has
 * come. Rings the clinic bell + Web Push (HIGH priority).
 *
 * Idempotent: only picks up pending rows with notified_at IS NULL, and
 * stamps notified_at on dispatch — so re-running (or the 10-min overlap)
 * never double-sends. Scheduled from bootstrap/app.php every ten minutes.
 */
class DispatchContactReminders extends Command
{
    protected $signature = 'saerha:dispatch-contact-reminders {--dry-run : Count due reminders without dispatching}';
    protected $description = 'Notify clinics about contact reminders whose scheduled time has arrived.';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $due = CustomerReminder::due()
            ->with(['clinic', 'customer:id,name,phone', 'assigneeMember:id,name'])
            ->limit(500)
            ->get();

        if ($this->option('dry-run')) {
            $this->warn("DRY-RUN: {$due->count()} reminder(s) due — none dispatched.");
            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($due as $reminder) {
            $clinic = $reminder->clinic;
            if (! $clinic) {
                // Orphaned (clinic hard-deleted) — stamp so we skip it next pass.
                $reminder->forceFill(['notified_at' => now()])->save();
                continue;
            }

            $customerName = $reminder->customer?->name
                ?: $reminder->customer?->phone
                ?: '—';

            // Suffix folds the optional assignee + note into the body. When
            // both are empty it collapses to a trailing period.
            $assigneePart = $reminder->assigneeMember
                ? __('notifications.reminder.assigned_to', ['name' => $reminder->assigneeMember->name])
                : '';
            $note = trim((string) $reminder->note);
            $notePart = $note !== '' ? ' — ' . $note : '';
            $combined = $assigneePart . $notePart;
            $noteSuffix = $combined !== '' ? $combined : '.';

            $notification = $dispatcher->dispatch(NotificationEvent::CONTACT_REMINDER_DUE, $clinic, [
                'customer'    => $customerName,
                'customer_id' => $reminder->customer_id,
                'note_suffix' => $noteSuffix,
            ]);

            // Stamp regardless: a failed dispatch is logged inside the
            // dispatcher, and we don't want a poison row retried forever.
            $reminder->forceFill(['notified_at' => now()])->save();

            if ($notification !== null) {
                $sent++;
            }
        }

        $this->info("Contact reminders dispatched: {$sent} / {$due->count()} due.");

        return self::SUCCESS;
    }
}
