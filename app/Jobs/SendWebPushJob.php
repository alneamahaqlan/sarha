<?php

namespace App\Jobs;

use App\Models\PlatformNotification;
use App\Services\WebPushSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queues the actual VAPID-signed POST so the originating request
 * (a booking create, a complaint reply) returns in <50ms instead of
 * waiting for the push service round-trip (~150-500ms).
 *
 * `tries=1` matches the queue:listen config in composer.json — push
 * delivery is best-effort; the in-app row already exists, so a failed
 * push isn't a lost notification, just a missing tab-closed buzz.
 */
class SendWebPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(public PlatformNotification $notification) {}

    public function handle(WebPushSender $sender): void
    {
        $sender->sendToNotifiable($this->notification);
    }
}
