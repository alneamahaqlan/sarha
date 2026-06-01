<?php

namespace App\Services\Messaging\Selectors;

use App\Contracts\Messaging\WhatsAppSenderSelector;
use App\Models\WhatsAppSender;

/**
 * Professional sender rotation: among the active, credentialed and healthy
 * numbers, prefer the configured priority, then the one used longest ago
 * (least-recently-used). This spreads traffic evenly across the registered
 * numbers and, together with the circuit breaker in the `healthy` scope,
 * routes around a number that has started failing.
 */
class LeastRecentlyUsedSenderSelector implements WhatsAppSenderSelector
{
    public function select(): ?WhatsAppSender
    {
        return WhatsAppSender::query()
            ->active()
            ->withCredentials()
            ->healthy()
            ->orderBy('priority')
            ->orderByRaw('last_used_at IS NULL DESC') // never-used numbers first
            ->orderBy('last_used_at')                 // then least-recently-used
            ->first();
    }
}
