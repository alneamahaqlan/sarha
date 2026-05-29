<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class OfferResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'clinic_id'      => $this->clinic_id,
            'service_id'     => $this->service_id,
            'type'           => $this->type,
            'title'          => $this->title,
            'description'    => $this->description,
            'image'          => $this->image,
            // Resolved image URL for direct rendering — falls back to the
            // linked service's image when the admin didn't upload a
            // dedicated one for the offer.
            'image_url'      => $this->resolveImageUrl(),
            'old_price'      => $this->old_price !== null ? (float) $this->old_price : null,
            'price'          => $this->price !== null ? (float) $this->price : null,
            'discount_percentage' => $this->discountPercentage(),
            'starts_at'      => $this->starts_at?->toIso8601String(),
            'ends_at'        => $this->ends_at?->toIso8601String(),
            'is_featured'    => (bool) $this->is_featured,
            'is_active'      => (bool) $this->is_active,
            'sort_order'     => (int) $this->sort_order,
            // Derived bucket — used by the admin grid for filter chips so
            // the client doesn't recompute the "active/scheduled/expired"
            // rule. Single source of truth lives in Offer::status().
            'status'         => $this->status(),
            'service'        => $this->whenLoaded('service', fn () => $this->service ? [
                'id'    => $this->service->id,
                'name'  => $this->service->name,
                'price' => (float) $this->service->price,
                'image' => $this->service->image,
            ] : null),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }

    private function resolveImageUrl(): ?string
    {
        $path = $this->effectiveImage();
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
