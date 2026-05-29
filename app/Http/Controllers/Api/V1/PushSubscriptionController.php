<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Endpoints the Service Worker layer uses to manage a browser's
 * Push subscription.
 *
 *   GET    /api/v1/push/vapid-public-key   → static config
 *   POST   /api/v1/push/subscribe          → upsert by endpoint_hash
 *   DELETE /api/v1/push/unsubscribe        → remove by endpoint
 *
 * Auth is multi-guard (admin / clinic / web) — whichever session
 * cookie the browser presents identifies the owner of the new
 * subscription. The polymorphic columns are filled accordingly.
 */
class PushSubscriptionController extends Controller
{
    public function vapidPublicKey(): JsonResponse
    {
        return response()->json([
            'key' => config('services.webpush.vapid_public_key'),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $owner = $this->resolveOwner($request);
        if (! $owner) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'endpoint'         => 'required|string|max:2048',
            'keys.p256dh'      => 'required|string|max:88',
            'keys.auth'        => 'required|string|max:24',
            'content_encoding' => 'nullable|string|in:aesgcm,aes128gcm',
        ]);

        $endpoint = $data['endpoint'];
        $hash = PushSubscription::hashFor($endpoint);

        // Upsert on endpoint_hash — re-subscribing the same browser tab
        // updates the owner + keys (e.g., after a login as a different role).
        $sub = PushSubscription::updateOrCreate(
            ['endpoint_hash' => $hash],
            [
                'subscribable_type' => $owner::class,
                'subscribable_id'   => $owner->getKey(),
                'endpoint'          => $endpoint,
                'p256dh_key'        => $data['keys']['p256dh'],
                'auth_key'          => $data['keys']['auth'],
                'content_encoding'  => $data['content_encoding'] ?? 'aesgcm',
                'user_agent'        => substr((string) $request->userAgent(), 0, 255),
                'last_used_at'      => now(),
            ],
        );

        return response()->json(['data' => ['id' => $sub->id]]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => 'required|string|max:2048']);
        PushSubscription::where('endpoint_hash', PushSubscription::hashFor($data['endpoint']))->delete();
        return response()->json(['data' => ['ok' => true]]);
    }

    /** Resolve the authenticated owner from any of the 3 supported guards. */
    private function resolveOwner(Request $request)
    {
        foreach (['admin', 'clinic', 'web'] as $guard) {
            if ($u = Auth::guard($guard)->user()) {
                return $u;
            }
        }
        return null;
    }
}
