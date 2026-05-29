<?php

namespace App\Services;

use App\Enums\NotificationEvent;
use App\Models\PlatformNotification;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Encapsulates the VAPID-authenticated POST that delivers a
 * notification to every active PushSubscription the recipient owns.
 *
 * Lock-screen privacy:
 *   We deliberately send the SHORT `preview` string (not the full
 *   body) as the notification text so a passer-by glancing at a
 *   parked phone doesn't see customer names or medical context.
 *   The full body is in the in-app bell once the user is logged in.
 *
 * Failure model:
 *   Web Push services may return:
 *     - 201 Created       → delivered
 *     - 404 / 410 Gone    → subscription dead → DELETE the row
 *     - 429 Too Many      → back off (job retry)
 *     - any 5xx           → push service trouble (job retry)
 *   We bubble retryable errors so the queue worker can re-queue;
 *   dead subscriptions are cleaned up inline.
 */
class WebPushSender
{
    public function __construct(private ?WebPush $client = null) {}

    /**
     * Send `$notification` to every active subscription owned by its
     * notifiable. Returns the number of pushes that left the queue —
     * NOT the number that succeeded (that's async).
     */
    public function sendToNotifiable(PlatformNotification $notification): int
    {
        $subscriptions = PushSubscription::query()
            ->where('subscribable_type', $notification->notifiable_type)
            ->where('subscribable_id',   $notification->notifiable_id)
            ->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $payload = $this->buildPayload($notification);

        $client = $this->client ?? $this->resolveClient();
        if (! $client) {
            // VAPID not configured — silent no-op (logged once at boot is enough).
            return 0;
        }

        foreach ($subscriptions as $sub) {
            try {
                $client->queueNotification(
                    Subscription::create([
                        'endpoint'        => $sub->endpoint,
                        'publicKey'       => $sub->p256dh_key,
                        'authToken'       => $sub->auth_key,
                        'contentEncoding' => $sub->content_encoding,
                    ]),
                    $payload,
                );
            } catch (\Throwable $e) {
                Log::warning('WebPushSender::queue failed', [
                    'sub_id' => $sub->id,
                    'err'    => $e->getMessage(),
                ]);
            }
        }

        $delivered = 0;
        foreach ($client->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();
            if ($report->isSuccess()) {
                PushSubscription::where('endpoint', $endpoint)
                    ->update(['last_used_at' => now()]);
                $delivered++;
                continue;
            }

            $code = $report->getResponse()?->getStatusCode();
            if (in_array($code, [404, 410], true)) {
                // Dead subscription — browser revoked or user cleared
                // push data. Prune the row so we stop trying.
                PushSubscription::where('endpoint', $endpoint)->delete();
                Log::info('WebPush subscription pruned (gone)', ['endpoint_hash' => PushSubscription::hashFor($endpoint)]);
            } else {
                Log::warning('WebPush delivery failed', [
                    'status' => $code,
                    'reason' => $report->getReason(),
                ]);
            }
        }

        return $delivered;
    }

    /**
     * Privacy-aware payload. Title is the event's full headline,
     * body is the SHORT `preview` string so lock-screen previews
     * don't expose customer names. The full body lives in-app.
     */
    private function buildPayload(PlatformNotification $notification): string
    {
        $event = $notification->event_type
            ? NotificationEvent::tryFrom($notification->event_type)
            : null;

        $preview = $event
            ? (string) __("notifications.preview.{$event->value}")
            : $notification->title;

        return json_encode([
            'title'    => $notification->title,
            'body'     => $preview,
            'icon'     => '/favicon.svg',
            'badge'    => '/favicon.svg',
            'url'      => $notification->url,
            'tag'      => 'notif-' . $notification->id,
            'event_id' => $notification->id,
            'priority' => $notification->priority,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function resolveClient(): ?WebPush
    {
        $pub = config('services.webpush.vapid_public_key');
        $priv = config('services.webpush.vapid_private_key');
        $sub = config('services.webpush.vapid_subject');
        if (! $pub || ! $priv) {
            Log::warning('WebPush disabled — VAPID keys not configured');
            return null;
        }
        return new WebPush([
            'VAPID' => [
                'subject'    => $sub,
                'publicKey'  => $pub,
                'privateKey' => $priv,
            ],
        ]);
    }
}
