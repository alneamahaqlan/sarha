<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'admin_id'   => $this->admin_id,
            'admin_name' => $this->admin_name,
            'action'     => $this->action,
            'model_type' => $this->model_type,
            'model_basename' => $this->model_type ? class_basename($this->model_type) : null,
            'model_id'   => $this->model_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
