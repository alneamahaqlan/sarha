<?php

namespace App\Events;

use App\Models\PlatformNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a PlatformNotification row is created via the
 * NotificationDispatcher. Reverb pushes the payload over the
 * recipient's private channel so the bell can update without polling.
 *
 * `ShouldBroadcastNow` (not `ShouldBroadcast`) — we run the dispatch
 * inline rather than queueing because notifications need to land in
 * the bell within the same second the originating action completes.
 * A queued dispatch on a slow worker would feel laggy to the user.
 *
 * Channel naming mirrors routes/channels.php:
 *   App\Models\Admin   → admin.{id}
 *   App\Models\Clinic  → clinic.{id}
 *   App\Models\User    → user.{id}
 */
class NotificationDispatched implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PlatformNotification $notification) {}

    public function broadcastOn(): array
    {
        $class = $this->notification->notifiable_type;
        $id    = $this->notification->notifiable_id;

        return [match ($class) {
            \App\Models\Admin::class  => new PrivateChannel("admin.{$id}"),
            \App\Models\Clinic::class => new PrivateChannel("clinic.{$id}"),
            \App\Models\User::class   => new PrivateChannel("user.{$id}"),
            default                   => new Channel("public-fallback.{$id}"),
        }];
    }

    public function broadcastAs(): string
    {
        // Stable name the frontend listens for. The leading dot signals
        // Echo NOT to namespace it under App\Events\.
        return 'notification.created';
    }

    /** Payload the browser receives — same shape the React bell already renders. */
    public function broadcastWith(): array
    {
        $n = $this->notification;
        return [
            'id'         => $n->id,
            'event_type' => $n->event_type,
            'icon'       => $n->icon,
            'url'        => $n->url,
            'priority'   => $n->priority,
            'title'      => $n->title,
            'body'       => $n->body,
            'created_at' => $n->created_at?->toIso8601String(),
            // `read_at` is always null for a fresh notification but we
            // emit it explicitly so the React reducer doesn't need a
            // shape-tolerant branch.
            'read_at'    => null,
        ];
    }
}
