<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ClinicResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'slug'                   => $this->slug,
            'phone'                  => $this->phone,
            'email'                  => $this->email,
            'city_id'                => $this->city_id,
            'address'                => $this->address,
            'latitude'               => $this->latitude,
            'longitude'              => $this->longitude,
            'google_place_id'        => $this->google_place_id,
            'description'            => $this->description,
            'logo'                   => $this->logo,
            'gallery'                => $this->gallery,
            'website'                => $this->website,
            'instagram'              => $this->instagram,
            'twitter'                => $this->twitter,
            'snapchat'               => $this->snapchat,
            'status'                 => $this->status,
            'subscription_type'      => $this->subscription_type,
            'subscription_starts_at' => $this->subscription_starts_at?->toIso8601String(),
            'subscription_ends_at'   => $this->subscription_ends_at?->toIso8601String(),
            'rejection_reason'       => $this->rejection_reason,
            'is_featured'            => (bool) $this->is_featured,
            'sort_order'             => (int) $this->sort_order,
            'city'                   => $this->whenLoaded('city', fn() => $this->city ? [
                'id'   => $this->city->id,
                'name' => $this->city->name,
            ] : null),
            'created_at'             => $this->created_at?->toIso8601String(),
            'updated_at'             => $this->updated_at?->toIso8601String(),
        ];
    }
}
