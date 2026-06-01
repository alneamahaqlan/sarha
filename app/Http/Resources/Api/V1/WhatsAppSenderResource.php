<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Exposes a WhatsApp sender to the admin panel. The token is a secret and is
 * never returned — only a `token_set` flag tells the UI whether one is stored
 * (same masking pattern as encrypted system settings).
 */
class WhatsAppSenderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'label'          => $this->label,
            'phone'          => $this->phone,
            'provider'       => $this->provider,
            'profile_id'     => $this->profile_id,
            'token_set'      => filled($this->getRawOriginal('token')),
            'is_active'      => (bool) $this->is_active,
            'priority'       => (int) $this->priority,
            'failure_count'  => (int) $this->failure_count,
            'last_used_at'   => $this->last_used_at?->toIso8601String(),
            'last_failed_at' => $this->last_failed_at?->toIso8601String(),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
