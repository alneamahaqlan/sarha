<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'clinic_id'      => $this->clinic_id,
            'sub_clinic_id'  => $this->sub_clinic_id,
            'category_id'    => $this->category_id,
            'sub_clinic'     => $this->whenLoaded('subClinic', fn () => $this->subClinic ? [
                'id'   => $this->subClinic->id,
                'name' => $this->subClinic->name,
            ] : null),
            'category'       => $this->whenLoaded('category', fn () => $this->category ? [
                'id'      => $this->category->id,
                'name'    => $this->category->name,
                'name_en' => $this->category->name_en,
                'emoji'   => $this->category->emoji,
                'slug'    => $this->category->slug,
            ] : null),
            'name'               => $this->name,
            'description'        => $this->description,
            'price'              => (float) $this->price,
            'old_price'          => $this->old_price !== null ? (float) $this->old_price : null,
            'offer_expires_at'   => $this->offer_expires_at?->toIso8601String(),
            'is_featured_offer'  => (bool) $this->is_featured_offer,
            'has_active_offer'   => $this->hasActiveOffer(),
            'discount_percentage'=> $this->discountPercentage(),
            'image'              => $this->image,
            'is_active'          => (bool) $this->is_active,
            'sort_order'         => (int) $this->sort_order,
            'clinic'             => $this->whenLoaded('clinic', fn() => [
                'id'   => $this->clinic->id,
                'name' => $this->clinic->name,
            ]),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
