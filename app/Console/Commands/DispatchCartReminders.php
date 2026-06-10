<?php

namespace App\Console\Commands;

use App\Enums\NotificationEvent;
use App\Models\CartItem;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\NotificationDispatcher;
use App\Services\SmsService;
use Illuminate\Console\Command;

/**
 * Nudges customers who left items unbooked in their cart: one SMS plus a
 * CART_REMINDER_DUE platform notification (bell + Web Push) per customer.
 *
 * Idempotent: only picks up abandoned rows (booked_at NULL) that have never
 * been reminded (last_reminder_at NULL) and aged past the configured window,
 * and stamps last_reminder_at on dispatch — so re-runs never double-nudge.
 * Scheduled hourly from bootstrap/app.php.
 */
class DispatchCartReminders extends Command
{
    protected $signature = 'saerha:dispatch-cart-reminders {--dry-run : Count due carts without dispatching}';
    protected $description = 'Remind customers about items left unbooked in their cart (SMS + Web Push).';

    public function handle(NotificationDispatcher $dispatcher, SmsService $sms): int
    {
        // How long an item must sit unbooked before the first nudge (hours).
        $hours = (int) SystemSetting::get('cart_reminder_hours', 24);

        $items = CartItem::query()
            ->whereNull('booked_at')
            ->whereNull('last_reminder_at')
            ->where('created_at', '<=', now()->subHours($hours))
            ->with('user:id,name,phone,is_active')
            ->limit(500)
            ->get();

        // One nudge per customer, even if they have several stale items.
        $byUser = $items->groupBy('user_id');

        if ($this->option('dry-run')) {
            $this->warn("DRY-RUN: {$byUser->count()} customer(s) with {$items->count()} stale item(s) — none dispatched.");
            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($byUser as $userId => $userItems) {
            /** @var User|null $user */
            $user = $userItems->first()->user;
            $count = $userItems->count();

            // Stamp first so a mid-loop failure never leaves rows eligible forever.
            CartItem::whereIn('id', $userItems->pluck('id'))->update(['last_reminder_at' => now()]);

            if (! $user || ! $user->is_active) {
                continue;
            }

            // SMS — guaranteed channel (we have the verified phone).
            if ($user->phone) {
                $sms->send($user->phone, __('site.cart_reminder_sms', ['count' => $count]));
            }

            // Bell + Web Push (only reaches subscribed devices; best-effort).
            $notification = $dispatcher->dispatch(NotificationEvent::CART_REMINDER_DUE, $user, [
                'count' => $count,
            ]);

            if ($notification !== null) {
                $sent++;
            }
        }

        $this->info("Cart reminders dispatched: {$sent} / {$byUser->count()} customer(s).");

        return self::SUCCESS;
    }
}
