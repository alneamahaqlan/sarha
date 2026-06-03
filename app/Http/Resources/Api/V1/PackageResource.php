<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'clinic_id'           => $this->clinic_id,
            'name'                => $this->name,
            'description'         => $this->description,
            'price'               => (float) $this->price,
            'old_price'           => $this->old_price !== null ? (float) $this->old_price : null,
            'discount_percentage' => $this->discountPercentage(),
            'expires_at'          => optional($this->expires_at)->toDateString(),
            'is_active'           => (bool) $this->is_active,
            'sort_order'          => (int) $this->sort_order,
            'service_ids'         => $this->whenLoaded('services', fn () => $this->services->pluck('id')->values()),
            // Per-service note keyed by service id — repopulates the edit form.
            'service_notes'       => $this->whenLoaded('services', fn () => $this->services
                ->mapWithKeys(fn ($s) => [(string) $s->id => $s->pivot->note])->all()),
            'services'            => $this->whenLoaded('services', fn () => $this->services->map(fn ($s) => [
                'id'   => $s->id,
                'name' => $s->name,
                'note' => $s->pivot->note,
            ])->values()),
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
        ];
    }
}
