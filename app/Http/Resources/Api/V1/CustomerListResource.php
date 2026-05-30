<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight row for the Customers Hub table. Counters come from
 * the Customer entity's persisted columns (no live recomputation).
 */
class CustomerListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'phone'                => $this->phone,
            'email'                => $this->email,
            'total_bookings'       => $this->total_bookings,
            'completed_bookings'   => $this->completed_bookings,
            'total_complaints'     => $this->total_complaints,
            'total_quote_requests' => $this->total_quote_requests,
            'is_vip'               => $this->is_vip,
            'is_repeat'            => $this->is_repeat,
            'is_new'               => $this->is_new,
            'has_prior_complaint'  => $this->has_prior_complaint,
            'first_seen_at'        => $this->first_seen_at?->toIso8601String(),
            'last_seen_at'         => $this->last_seen_at?->toIso8601String(),
            'last_interaction_at'  => $this->last_interaction_at?->toIso8601String(),
            'last_interaction_type'=> $this->last_interaction_type,
            'tags' => $this->whenLoaded('tags', fn() => $this->tags->map(fn($t) => [
                'id'    => $t->id,
                'label' => $t->label,
                'color' => $t->color,
            ])->all(), []),
            'has_notes' => $this->whenLoaded('notes', fn() => $this->notes->isNotEmpty(), null),
        ];
    }
}
