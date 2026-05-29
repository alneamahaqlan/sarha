<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per browser × device the user has authorised for Web Push.
 * Polymorphic so the same table backs every role.
 *
 * Endpoints are upserted on `endpoint_hash` (sha256 of the endpoint
 * URL) — the Push API guarantees one endpoint per (browser, vapid key)
 * pair, so re-subscribing from the same tab dedupes naturally.
 */
class PushSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscribable_type', 'subscribable_id',
        'endpoint', 'endpoint_hash',
        'p256dh_key', 'auth_key', 'content_encoding',
        'user_agent', 'last_used_at',
    ];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function subscribable()
    {
        return $this->morphTo();
    }

    /**
     * Build the canonical hash used for upsert. We don't index the
     * full endpoint (can be 200+ chars) — a sha256 short-circuit lets
     * MySQL keep the index small while still being unique.
     */
    public static function hashFor(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
