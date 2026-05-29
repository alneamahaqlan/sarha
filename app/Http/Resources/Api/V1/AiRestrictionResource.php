<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Category;
use App\Models\Clinic;
use Illuminate\Http\Resources\Json\JsonResource;

class AiRestrictionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'type'                => $this->type,
            'value'               => $this->value,
            // For blocklist rows, resolve a human label so the admin UI
            // doesn't have to fan out lookups per row. Cheap because the
            // admin list is small (dozens at most).
            'value_label'         => $this->resolveLabel(),
            'response_override'   => $this->response_override,
            'is_active'           => (bool) $this->is_active,
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }

    private function resolveLabel(): ?string
    {
        if ($this->type === 'clinic_blocklist') {
            return Clinic::find((int) $this->value)?->name;
        }
        if ($this->type === 'category_blocklist') {
            $cat = Category::find((int) $this->value);
            return $cat?->display_name ?? $cat?->name;
        }
        return null;
    }
}
