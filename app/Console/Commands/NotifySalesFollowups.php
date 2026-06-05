<?php

namespace App\Console\Commands;

use App\Enums\NotificationEvent;
use App\Models\Admin;
use App\Models\SalesLead;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;

/**
 * Rings the bell when a sales lead's scheduled follow-up time has passed.
 *
 * Assigned leads notify their owner; unassigned leads notify every active
 * admin so nothing falls through the cracks.
 *
 * Idempotent: only picks up leads with followup_notified_at IS NULL, and
 * stamps it on dispatch. The controller clears the stamp whenever an admin
 * reschedules next_follow_up_at, so the next due date re-arms the reminder.
 * Scheduled hourly from bootstrap/app.php.
 */
class NotifySalesFollowups extends Command
{
    protected $signature = 'saerha:notify-sales-followups {--dry-run : Count due follow-ups without dispatching}';
    protected $description = 'Notify admins about sales leads whose follow-up time has passed.';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $due = SalesLead::query()
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now())
            ->whereNull('followup_notified_at')
            ->whereNotIn('status', SalesLead::TERMINAL_STATUSES)
            ->with('assignedAdmin:id,name,is_active')
            ->limit(500)
            ->get();

        if ($this->option('dry-run')) {
            $this->warn("DRY-RUN: {$due->count()} overdue follow-up(s) — none dispatched.");
            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($due as $lead) {
            $data = [
                'lead_id'         => $lead->id,
                'lead'            => $lead->clinic_name,
                'assignee_suffix' => '.',
            ];

            if ($lead->assignedAdmin && $lead->assignedAdmin->is_active) {
                if ($dispatcher->dispatch(NotificationEvent::SALES_FOLLOWUP_DUE, $lead->assignedAdmin, $data)) {
                    $sent++;
                }
            } else {
                // Unassigned (or assignee deactivated) — fan out to all admins.
                $data['assignee_suffix'] = ' ' . __('notifications.sales_followup_unassigned');
                $sent += $dispatcher->dispatchToAllAdmins(NotificationEvent::SALES_FOLLOWUP_DUE, $data);
            }

            $lead->forceFill(['followup_notified_at' => now()])->save();
        }

        $this->info("Sales follow-up reminders dispatched: {$sent} ({$due->count()} leads due).");

        return self::SUCCESS;
    }
}
