<?php

namespace App\Http\Resources\Api\V1;

use App\Models\SystemSetting;
use App\Services\PiiMasker;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row in the conversation log table. PII is masked by default unless
 * the admin turned off `ai_pii_masking_enabled` globally. Passing
 * `?reveal=1` from the admin UI per-call also flips $reveal so
 * investigators can see raw text on demand.
 *
 * Effective rule: show raw text when EITHER the global masking flag is
 * off OR the per-request reveal flag is set.
 */
class AiAssistantLogResource extends JsonResource
{
    public function __construct(mixed $resource, private readonly bool $reveal = false)
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        $masker = app(PiiMasker::class);

        $maskingEnabled = (bool) SystemSetting::get('ai_pii_masking_enabled', true);
        $showRaw = $this->reveal || ! $maskingEnabled;

        return [
            'id'              => $this->id,
            'conversation_id' => $this->conversation_id,
            'user_id'         => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id'    => $this->user->id,
                'name'  => $showRaw ? $this->user->name  : $masker->maskName($this->user->name),
                'phone' => $showRaw ? $this->user->phone : $masker->maskPhone($this->user->phone),
            ] : null),
            'visitor_id'      => $this->visitor_id,
            'guard'           => $this->guard,
            'query'           => $showRaw ? $this->query : $masker->mask($this->query),
            'reply'           => $showRaw ? $this->reply : $masker->mask($this->reply),
            'kind'            => $this->kind,
            'provider'        => $this->provider,
            'model'           => $this->model,
            'tokens_in'       => (int) $this->tokens_in,
            'tokens_out'      => (int) $this->tokens_out,
            'response_ms'     => $this->response_ms !== null ? (int) $this->response_ms : null,
            'locale'          => $this->locale,
            'was_blocked'     => (bool) $this->was_blocked,
            'was_emergency'   => (bool) $this->was_emergency,
            'clinics'         => $this->whenLoaded('clinics', fn () => $this->clinics->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()),
            'categories'      => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->display_name ?? $c->name])->values()),
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
