<?php

namespace App\Console\Commands;

use App\Enums\NotificationEvent;
use App\Models\Admin;
use App\Models\Clinic;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;

/**
 * End-to-end smoke test for the unified-notification foundation:
 *
 *   php artisan notify:test                       # interactive picker
 *   php artisan notify:test booking_created       # first matching clinic
 *   php artisan notify:test booking_created --clinic=1
 *   php artisan notify:test ai_emergency          # fans out to all admins
 *
 * The command resolves a sensible default recipient when one isn't
 * passed (first Clinic / first User / all Admins) so the dev loop
 * stays one command long. Exits non-zero if no recipient could be
 * resolved or the dispatcher returned null.
 */
class NotifyTestCommand extends Command
{
    protected $signature = 'notify:test
        {event? : NotificationEvent value (e.g. booking_created)}
        {--clinic= : Clinic ID for clinic-targeted events}
        {--user= : User ID for user-targeted events}
        {--admin= : Admin ID (when omitted, fans out to all active admins)}';

    protected $description = 'Send a sample notification through the unified dispatcher';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $eventValue = $this->argument('event') ?: $this->askEvent();
        $event = NotificationEvent::tryFrom($eventValue);
        if (! $event) {
            $this->error("Unknown event '{$eventValue}'. Available:");
            foreach (NotificationEvent::cases() as $c) {
                $this->line("  - {$c->value}");
            }
            return self::FAILURE;
        }

        $this->line("Event:      <fg=cyan>{$event->value}</>");
        $this->line('Target:     ' . class_basename($event->targetClass()));
        $this->line("Priority:   {$event->priority()->value}");

        $sampleData = $this->sampleDataFor($event);
        $this->line('Sample data: ' . json_encode($sampleData, JSON_UNESCAPED_UNICODE));

        // Resolve recipient(s) based on the event's target class.
        $count = match ($event->targetClass()) {
            Clinic::class => $this->dispatchToClinic($event, $sampleData, $dispatcher),
            User::class   => $this->dispatchToUser($event, $sampleData, $dispatcher),
            Admin::class  => $this->dispatchToAdmin($event, $sampleData, $dispatcher),
        };

        if ($count === 0) {
            $this->error('Dispatch failed — no notification was created.');
            return self::FAILURE;
        }

        $this->info("✓ Sent {$count} notification(s). Check platform_notifications table.");
        return self::SUCCESS;
    }

    private function askEvent(): string
    {
        $choices = array_map(fn ($c) => $c->value, NotificationEvent::cases());
        return $this->choice('Which event?', $choices, 0);
    }

    private function sampleDataFor(NotificationEvent $event): array
    {
        return match ($event) {
            NotificationEvent::BOOKING_CREATED,
            NotificationEvent::BOOKING_CANCELLED_BY_USER => [
                'customer'       => 'سامي تجريبي',
                'reference_code' => 'SAR-DEMO01',
            ],
            NotificationEvent::COMPLAINT_CREATED => [
                'customer' => 'سامي تجريبي',
            ],
            NotificationEvent::QUOTE_CREATED,
            NotificationEvent::QUOTE_REPLIED => [
                'customer' => 'سامي تجريبي',
                'service'  => 'تنظيف الأسنان',
                'clinic'   => 'مركز الرياض للأسنان',
            ],
            NotificationEvent::BOOKING_CONFIRMED => [
                'clinic'         => 'مركز الرياض للأسنان',
                'reference_code' => 'SAR-DEMO01',
            ],
            NotificationEvent::COMPLAINT_REPLIED => [
                'clinic' => 'مركز الرياض للأسنان',
            ],
            NotificationEvent::CLINIC_PENDING_APPROVAL => [
                'clinic' => 'مجمع طبي تجريبي جديد',
            ],
            NotificationEvent::AI_EMERGENCY => [
                'customer'        => 'سامي تجريبي',
                'conversation_id' => 'demo-' . substr((string) microtime(true), -8),
            ],
        };
    }

    private function dispatchToClinic(NotificationEvent $event, array $data, NotificationDispatcher $dispatcher): int
    {
        $id = $this->option('clinic');
        $clinic = $id ? Clinic::find($id) : Clinic::where('status', 'active')->first();
        if (! $clinic) {
            $this->error('No clinic resolved (try --clinic=<id>).');
            return 0;
        }
        $this->line("Recipient:  Clinic #{$clinic->id} — {$clinic->name}");
        return $dispatcher->dispatch($event, $clinic, $data) ? 1 : 0;
    }

    private function dispatchToUser(NotificationEvent $event, array $data, NotificationDispatcher $dispatcher): int
    {
        $id = $this->option('user');
        $user = $id ? User::find($id) : User::where('is_active', true)->first();
        if (! $user) {
            $this->error('No user resolved (try --user=<id>).');
            return 0;
        }
        $this->line("Recipient:  User #{$user->id} — {$user->name}");
        return $dispatcher->dispatch($event, $user, $data) ? 1 : 0;
    }

    private function dispatchToAdmin(NotificationEvent $event, array $data, NotificationDispatcher $dispatcher): int
    {
        $id = $this->option('admin');
        if ($id) {
            $admin = Admin::find($id);
            if (! $admin) {
                $this->error("No admin #{$id}.");
                return 0;
            }
            $this->line("Recipient:  Admin #{$admin->id} — {$admin->name}");
            return $dispatcher->dispatch($event, $admin, $data) ? 1 : 0;
        }
        $count = $dispatcher->dispatchToAllAdmins($event, $data);
        $this->line("Recipient:  all active admins ({$count} delivered)");
        return $count;
    }
}
