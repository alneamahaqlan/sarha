<?php

namespace App\Enums;

use App\Models\Admin;
use App\Models\Clinic;
use App\Models\User;

/**
 * Single-source catalog for all platform events that should ring a
 * notification bell. Each case carries:
 *   - target role (which user type receives it)
 *   - priority (drives channels + sound)
 *   - i18n keys for title/body
 *   - deep-link URL builder
 *
 * Adding a new event = adding a single case + its translation keys.
 * The dispatcher, web-push payload builder, and React UI all read
 * from here, so no parallel lists drift apart.
 */
enum NotificationEvent: string
{
    // ── Clinic-side events ─────────────────────────────────────────
    case BOOKING_CREATED            = 'booking_created';
    case BOOKING_CANCELLED_BY_USER  = 'booking_cancelled_by_user';
    case COMPLAINT_CREATED          = 'complaint_created';
    case QUOTE_CREATED              = 'quote_created';
    // A clinic-set "contact this patient now" reminder whose time has come.
    case CONTACT_REMINDER_DUE       = 'contact_reminder_due';
    // A general team task (no customer) whose time has come.
    case TASK_REMINDER_DUE          = 'task_reminder_due';

    // ── Clinic-side subscription lifecycle ─────────────────────────
    // Fired by the daily lifecycle command + SubscriptionService so
    // the clinic owner sees activation receipts, renewal reminders,
    // and the grace-period countdown without checking the admin.
    case SUBSCRIPTION_EXPIRING_SOON = 'subscription_expiring_soon';
    case SUBSCRIPTION_EXPIRED       = 'subscription_expired';
    case SUBSCRIPTION_ACTIVATED     = 'subscription_activated';
    case SUBSCRIPTION_CANCELLED     = 'subscription_cancelled';

    // ── User-side events ───────────────────────────────────────────
    case BOOKING_CONFIRMED          = 'booking_confirmed';
    case COMPLAINT_REPLIED          = 'complaint_replied';
    case QUOTE_REPLIED              = 'quote_replied';
    // The customer left items unbooked in their cart — gentle nudge to finish.
    case CART_REMINDER_DUE          = 'cart_reminder_due';

    // ── Admin-side events ──────────────────────────────────────────
    case CLINIC_PENDING_APPROVAL    = 'clinic_pending_approval';
    case AI_EMERGENCY               = 'ai_emergency';
    // A sales lead's scheduled follow-up time has passed.
    case SALES_FOLLOWUP_DUE         = 'sales_followup_due';

    /**
     * Which model class receives this event. Drives broadcast channel
     * naming + which notifiable_type column gets written.
     */
    public function targetClass(): string
    {
        return match ($this) {
            self::BOOKING_CREATED,
            self::BOOKING_CANCELLED_BY_USER,
            self::COMPLAINT_CREATED,
            self::QUOTE_CREATED,
            self::SUBSCRIPTION_EXPIRING_SOON,
            self::SUBSCRIPTION_EXPIRED,
            self::SUBSCRIPTION_ACTIVATED,
            self::SUBSCRIPTION_CANCELLED,
            self::CONTACT_REMINDER_DUE,
            self::TASK_REMINDER_DUE => Clinic::class,

            self::BOOKING_CONFIRMED,
            self::COMPLAINT_REPLIED,
            self::QUOTE_REPLIED,
            self::CART_REMINDER_DUE => User::class,

            self::CLINIC_PENDING_APPROVAL,
            self::AI_EMERGENCY,
            self::SALES_FOLLOWUP_DUE => Admin::class,
        };
    }

    public function priority(): NotificationPriority
    {
        return match ($this) {
            self::AI_EMERGENCY,
            self::SUBSCRIPTION_EXPIRED => NotificationPriority::URGENT,

            self::BOOKING_CREATED,
            self::BOOKING_CANCELLED_BY_USER,
            self::COMPLAINT_CREATED,
            self::BOOKING_CONFIRMED,
            self::CLINIC_PENDING_APPROVAL,
            self::CONTACT_REMINDER_DUE,
            self::TASK_REMINDER_DUE,
            self::SALES_FOLLOWUP_DUE,
            self::CART_REMINDER_DUE,
            self::SUBSCRIPTION_EXPIRING_SOON => NotificationPriority::HIGH,

            self::QUOTE_CREATED,
            self::COMPLAINT_REPLIED,
            self::QUOTE_REPLIED,
            self::SUBSCRIPTION_ACTIVATED,
            self::SUBSCRIPTION_CANCELLED => NotificationPriority::NORMAL,
        };
    }

    /** Lucide / heroicon name the bell renders next to the row. */
    public function icon(): string
    {
        return match ($this) {
            self::BOOKING_CREATED, self::BOOKING_CONFIRMED => 'calendar',
            self::BOOKING_CANCELLED_BY_USER                => 'calendar-x',
            self::COMPLAINT_CREATED, self::COMPLAINT_REPLIED => 'alert-triangle',
            self::QUOTE_CREATED, self::QUOTE_REPLIED         => 'dollar-sign',
            self::CONTACT_REMINDER_DUE                       => 'phone-call',
            self::TASK_REMINDER_DUE                          => 'list-checks',
            self::CART_REMINDER_DUE                          => 'shopping-bag',
            self::CLINIC_PENDING_APPROVAL                    => 'building-2',
            self::AI_EMERGENCY                               => 'siren',
            self::SALES_FOLLOWUP_DUE                         => 'phone-call',
            self::SUBSCRIPTION_EXPIRING_SOON                 => 'clock',
            self::SUBSCRIPTION_EXPIRED                       => 'alert-octagon',
            self::SUBSCRIPTION_ACTIVATED                     => 'check-circle',
            self::SUBSCRIPTION_CANCELLED                     => 'x-circle',
        };
    }

    /** Translated title — short headline that fits a push notification. */
    public function title(array $data = []): string
    {
        return (string) __("notifications.events.{$this->value}.title", $data);
    }

    /** Translated body — one sentence with placeholders filled. */
    public function body(array $data = []): string
    {
        return (string) __("notifications.events.{$this->value}.body", $data);
    }

    /**
     * Where the user lands when they click the notification. Returns
     * a path (not full URL) so the JS layer can build the final href
     * with the right host.
     */
    public function url(array $data = []): ?string
    {
        return match ($this) {
            self::BOOKING_CREATED,
            self::BOOKING_CANCELLED_BY_USER =>
                isset($data['reference_code'])
                    ? '/app/clinic/bookings?search=' . urlencode((string) $data['reference_code'])
                    : '/app/clinic/bookings',

            self::COMPLAINT_CREATED => '/app/clinic/complaints',
            self::QUOTE_CREATED     => '/app/clinic/price-quotes',

            // Lands on the reminders page; deep-links to the customer when
            // the payload carries their id so the clinic can act in one click.
            self::CONTACT_REMINDER_DUE => isset($data['customer_id'])
                ? '/app/clinic/customers/' . urlencode((string) $data['customer_id'])
                : '/app/clinic/reminders',

            // General task — always lands on the tasks/reminders page.
            self::TASK_REMINDER_DUE => '/app/clinic/reminders',

            self::BOOKING_CONFIRMED  => '/account/bookings',
            self::COMPLAINT_REPLIED  => '/account/complaints',
            self::QUOTE_REPLIED      => '/account/quotes',
            self::CART_REMINDER_DUE  => '/cart',

            self::CLINIC_PENDING_APPROVAL => '/app/admin/clinics?status=pending',

            // Deep-link straight to the lead (the index auto-opens its
            // detail sheet from ?lead=ID).
            self::SALES_FOLLOWUP_DUE => isset($data['lead_id'])
                ? '/app/admin/sales-leads?lead=' . urlencode((string) $data['lead_id'])
                : '/app/admin/sales-leads',

            self::AI_EMERGENCY => isset($data['conversation_id'])
                ? '/app/admin/ai-center?conversation=' . urlencode((string) $data['conversation_id'])
                : '/app/admin/ai-center',

            // Subscription lifecycle — all four land the clinic owner on
            // their subscription page so they see status + the "تواصل
            // للترقية" button without an extra click.
            self::SUBSCRIPTION_EXPIRING_SOON,
            self::SUBSCRIPTION_EXPIRED,
            self::SUBSCRIPTION_ACTIVATED,
            self::SUBSCRIPTION_CANCELLED => '/app/clinic/subscription',
        };
    }

    /**
     * Channels this event flows through. Phase A returns database only
     * — phases B (broadcast) and C (webpush) extend this list per
     * priority without touching call sites.
     */
    public function channels(): array
    {
        return ['database'];
    }
}
