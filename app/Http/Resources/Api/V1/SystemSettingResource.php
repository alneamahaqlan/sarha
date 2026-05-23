<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class SystemSettingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'key'         => $this->key,
            'label'       => $this->label,
            'type'        => $this->type,
            'group'       => $this->group,
            'value'       => $this->value,
            'description' => $this->description,
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
