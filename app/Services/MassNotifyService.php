<?php

namespace App\Services;

use App\Models\Clinic;

/**
 * Single source of truth for the mass-notification broadcast.
 *
 * Extracted verbatim from MassNotify Filament page send() so both the page
 * and the API endpoint resolve the audience and dispatch via NotificationService
 * identically. No business-logic change.
 */
class MassNotifyService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * @param array{audience:string, priority:string, title:string, body:string} $payload
     * @return int Number of clinics notified.
     */
    public function send(array $payload): int
    {
        $recipients = match ($payload['audience']) {
            'premium'  => Clinic::publiclyVisible()->where('subscription_type', 'premium')->get(),
            'basic'    => Clinic::publiclyVisible()->where('subscription_type', 'basic')->get(),
            'expiring' => Clinic::publiclyVisible()
                ->where('subscription_ends_at', '<=', now()->addDays(10))->get(),
            default    => Clinic::publiclyVisible()->get(),
        };

        return $this->notifications->massNotify($recipients, [
            'type'     => 'mass_notice',
            'icon'     => 'heroicon-o-megaphone',
            'priority' => $payload['priority'],
            'title'    => $payload['title'],
            'body'     => $payload['body'],
        ]);
    }
}
