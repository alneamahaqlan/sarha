<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class CampaignRecipientResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'customer_id' => $this->customer_id,
            'name'        => $this->name_snapshot,
            'phone'       => $this->phone_snapshot,
            'status'      => $this->status,
            'sent_at'     => $this->sent_at?->toIso8601String(),
        ];
    }
}
