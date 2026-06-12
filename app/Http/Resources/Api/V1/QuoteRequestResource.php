<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Broadcast quote request as seen by a single clinic: the request details,
 * its targeted cities, the total reply count, and THIS clinic's own reply
 * (loaded into the `replies` relation, filtered to the clinic, if any).
 */
class QuoteRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $myReply = $this->whenLoaded('replies', fn () => $this->replies->first());

        return [
            'id'             => $this->id,
            'customer_name'  => $this->customer_name,
            // Phone is intentionally NOT exposed: clinics compete by replying
            // inside the platform and never get the customer's contact channel.
            'service_name'   => $this->service_name,
            'description'    => $this->description,
            'status'         => $this->status,
            'created_at'     => $this->created_at?->toIso8601String(),
            'cities'         => $this->whenLoaded('cities', fn () => $this->cities->map(fn ($c) => [
                'id'   => $c->id,
                'name' => $c->name,
            ])->values()),
            'categories'     => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($c) => [
                'id'   => $c->id,
                'name' => $c->name,
            ])->values()),
            'replies_count'  => $this->whenCounted('replies'),
            'my_reply'       => $myReply ? [
                'id'        => $myReply->id,
                'body'      => $myReply->body,
                'price'     => $myReply->price !== null ? (float) $myReply->price : null,
                'is_public' => (bool) $myReply->is_public,
            ] : null,
        ];
    }
}
