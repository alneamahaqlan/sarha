<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class AiResponseTemplateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'label'      => $this->label,
            'content'    => $this->content,
            'is_active'  => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
