<?php

namespace App\Services;

use App\Enums\NotificationEvent;
use App\Enums\NotificationPriority;
use App\Events\NotificationDispatched;
use App\Jobs\SendWebPushJob;
use App\Models\Admin;
use App\Models\PlatformNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point for all unified notifications.
 *
 *   $dispatcher->dispatch(NotificationEvent::BOOKING_CREATED, $clinic, [
 *       'reference_code' => $booking->reference_code,
 *       'customer'       => $booking->customer_name,
 *   ]);
 *
 * Phase A:
 *   - writes a PlatformNotification row via the `database` channel
 *
 * Phases B + C (later) will extend by:
 *   - broadcasting to the recipient's private Reverb channel
 *   - delivering via Web Push to every active PushSubscription
 *
 * Call sites never reference the channels — they hand over the event
 * + target + data and trust the catalog to decide who hears what.
 */
class NotificationDispatcher
{
    /**
     * Send a single event to one recipient model. Returns the
     * created PlatformNotification (or null on failure, since
     * notification failures must NEVER break the originating action).
     */
    public function dispatch(NotificationEvent $event, Model $recipient, array $data = []): ?PlatformNotification
    {
        // Catalog enforces the role-target invariant so a caller can't
        // accidentally send a clinic event to a User row.
        $expected = $event->targetClass();
        if (! $recipient instanceof $expected) {
            Log::warning('NotificationDispatcher::dispatch type mismatch', [
                'event'    => $event->value,
                'expected' => $expected,
                'got'      => $recipient::class,
            ]);
            return null;
        }

        try {
            $notification = PlatformNotification::create([
                'notifiable_type' => $recipient::class,
                'notifiable_id'   => $recipient->getKey(),
                // `type` (legacy free-form) stays in sync with `event_type`
                // so the existing NotificationBell that reads `type` keeps
                // showing the row until the React layer learns event_type.
                'type'            => $event->value,
                'event_type'      => $event->value,
                'icon'            => $event->icon(),
                'url'             => $event->url($data),
                'priority'        => $event->priority()->value,
                'title'           => $event->title($data),
                'body'            => $event->body($data),
                'data'            => $data,
            ]);

            // Broadcast the new notification on the recipient's
            // private channel so the bell updates without polling.
            // ShouldBroadcastNow → inline, no queue dependency.
            // Failure here is swallowed (DB row already persisted —
            // the bell catches up on the next polling tick / refresh).
            try {
                NotificationDispatched::dispatch($notification);
            } catch (\Throwable $e) {
                Log::warning('NotificationDispatched broadcast failed', [
                    'pn_id' => $notification->id,
                    'err'   => $e->getMessage(),
                ]);
            }

            // Web Push — queued so the originating request returns
            // immediately (the VAPID round-trip is ~150-500ms). INFO
            // priority is in-app only; everything HIGH and above goes
            // to the lock screen.
            try {
                $priority = NotificationPriority::tryFrom($notification->priority);
                if ($priority && $priority->shouldWebPush()) {
                    SendWebPushJob::dispatch($notification);
                }
            } catch (\Throwable $e) {
                Log::warning('WebPush queue failed', [
                    'pn_id' => $notification->id,
                    'err'   => $e->getMessage(),
                ]);
            }

            return $notification;
        } catch (\Throwable $e) {
            Log::warning('NotificationDispatcher::dispatch failed', [
                'event' => $event->value,
                'err'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Convenience helper: send a single event to every active admin.
     * Used by events whose target is "the admin team" rather than a
     * specific row (AI_EMERGENCY, CLINIC_PENDING_APPROVAL).
     */
    public function dispatchToAllAdmins(NotificationEvent $event, array $data = []): int
    {
        if ($event->targetClass() !== Admin::class) {
            Log::warning('dispatchToAllAdmins called with non-admin event', ['event' => $event->value]);
            return 0;
        }

        $admins = Admin::where('is_active', true)->get();
        $sent = 0;
        foreach ($admins as $admin) {
            if ($this->dispatch($event, $admin, $data)) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * Bulk dispatch — same event to many recipients (e.g., notify all
     * favorited-clinic owners about an offer). Each delivery is wrapped
     * individually so one failed write doesn't poison the rest.
     */
    public function dispatchMany(NotificationEvent $event, Collection $recipients, array $data = []): int
    {
        $sent = 0;
        foreach ($recipients as $recipient) {
            if ($this->dispatch($event, $recipient, $data)) {
                $sent++;
            }
        }
        return $sent;
    }
}
