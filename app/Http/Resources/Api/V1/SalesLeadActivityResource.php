<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class SalesLeadActivityResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'body'       => $this->body,
            'meta'       => $this->meta,
            'admin_name' => $this->admin_name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
