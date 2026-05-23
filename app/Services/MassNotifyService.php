<?php

namespace App\Services;

use App\Models\Clinic;
use Illuminate\Support\Facades\Mail;

/**
 * Single source of truth for the mass-notification broadcast.
 *
 * Extracted verbatim from MassNotify Filament page send() so both the page
 * and the API endpoint resolve the audience and dispatch via NotificationService
 * identically. Supports an optional delivery channel (in-app / email / both).
 */
class MassNotifyService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * @param array{audience:string, priority:string, title:string, body:string, channel?:string} $payload
     * @return int Number of clinics reached (in-app recipients; falls back to emailed count).
     */
    public function send(array $payload): int
    {
        $channel = $payload['channel'] ?? 'in_app';

        $recipients = match ($payload['audience']) {
            'premium'  => Clinic::publiclyVisible()->where('subscription_type', 'premium')->get(),
            'basic'    => Clinic::publiclyVisible()->where('subscription_type', 'basic')->get(),
            'expiring' => Clinic::publiclyVisible()
                ->where('subscription_ends_at', '<=', now()->addDays(10))->get(),
            default    => Clinic::publiclyVisible()->get(),
        };

        $inAppCount = 0;
        if ($channel === 'in_app' || $channel === 'both') {
            $inAppCount = $this->notifications->massNotify($recipients, [
                'type'     => 'mass_notice',
                'icon'     => 'heroicon-o-megaphone',
                'priority' => $payload['priority'],
                'title'    => $payload['title'],
                'body'     => $payload['body'],
            ]);
        }

        $emailCount = 0;
        if ($channel === 'email' || $channel === 'both') {
            $emailCount = $this->sendEmails($recipients, $payload['title'], $payload['body']);
        }

        // Report in-app reach when applicable, otherwise the emailed count.
        return $channel === 'email' ? $emailCount : $inAppCount;
    }

    private function sendEmails(iterable $recipients, string $title, string $body): int
    {
        $sent = 0;
        foreach ($recipients as $clinic) {
            $email = $clinic->email;
            if (! $email) continue;

            Mail::raw($body, function ($message) use ($email, $title) {
                $message->to($email)->subject($title);
            });
            $sent++;
        }
        return $sent;
    }
}
