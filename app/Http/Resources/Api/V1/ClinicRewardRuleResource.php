<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The clinic's single auto-grant rule for the config screen. Echoes the
 * linked offer/service inline so the form can render their names without
 * a second lookup.
 */
class ClinicRewardRuleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'enabled'        => (bool) $this->enabled,
            'type'           => $this->type,
            'offer_id'       => $this->offer_id,
            'service_id'     => $this->service_id,
            'discount_type'  => $this->discount_type,
            'discount_value' => $this->discount_value !== null ? (float) $this->discount_value : null,
            'validity_days'  => $this->validity_days,
            'offer'          => $this->whenLoaded('offer', fn () => $this->offer ? [
                'id'    => $this->offer->id,
                'title' => $this->offer->title,
            ] : null),
            'service'        => $this->whenLoaded('service', fn () => $this->service ? [
                'id'   => $this->service->id,
                'name' => $this->service->name,
            ] : null),
            'is_grantable'   => $this->resource->exists ? $this->isGrantable() : false,
        ];
    }
}
