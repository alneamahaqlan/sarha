<?php

namespace App\Http\Controllers\Api\V1\Shared;

use App\Http\Controllers\Controller;
use App\Models\PlatformNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Polymorphic notification bell — same model as the Filament NotificationBell
 * Livewire component. Recipient is resolved from whichever guard is active.
 */
class NotificationController extends Controller
{
    private function recipient()
    {
        foreach (['admin', 'clinic', 'web'] as $g) {
            if ($user = Auth::guard($g)->user()) {
                return $user;
            }
        }
        return null;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->recipient();
        if (! $user) return response()->json(['message' => 'Unauthenticated.'], 401);

        $query = PlatformNotification::forRecipient($user);

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        $limit = min(max((int) $request->input('limit', 20), 1), 100);
        $items = $query->orderByDesc('created_at')->limit($limit)->get();

        $unreadCount = PlatformNotification::forRecipient($user)->unread()->count();

        return response()->json([
            'data' => $items->map(fn (PlatformNotification $n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'icon'       => $n->icon,
                'url'        => $n->url,
                'priority'   => $n->priority,
                'title'      => $n->title,
                'body'       => $n->body,
                'data'       => $n->data,
                'is_read'    => (bool) $n->read_at,
                'read_at'    => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'meta' => ['unread_count' => $unreadCount],
        ]);
    }

    public function markRead(PlatformNotification $notification): JsonResponse
    {
        $user = $this->recipient();
        if (! $user) return response()->json(['message' => 'Unauthenticated.'], 401);

        if ($notification->notifiable_type !== $user::class
            || $notification->notifiable_id !== $user->getKey()) {
            return response()->json(['message' => 'Not yours.'], 403);
        }

        $notification->markAsRead();

        return response()->json(['data' => ['id' => $notification->id, 'is_read' => true]]);
    }

    public function markAllRead(): JsonResponse
    {
        $user = $this->recipient();
        if (! $user) return response()->json(['message' => 'Unauthenticated.'], 401);

        PlatformNotification::forRecipient($user)->unread()->update(['read_at' => now()]);

        return response()->json(['data' => ['unread_count' => 0]]);
    }
}
