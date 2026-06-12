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
            'sub_clinic'     => $this->whenLoaded('subClinic', fn () => $this->subClinic ? [
                'id'   => $this->subClinic->id,
                'name' => $this->subClinic->name,
            ] : null),
            // 1–5 specialties this service belongs to (many-to-many).
            'categories'     => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($c) => [
                'id'      => $c->id,
                'name'    => $c->name,
                'name_en' => $c->name_en,
                'emoji'   => $c->emoji,
                'slug'    => $c->slug,
            ])->values()),
            // Convenience: just the ids (used by edit forms).
            'category_ids'   => $this->whenLoaded('categories', fn () => $this->categories->pluck('id')->values()),
            // Doctors who provide this service (many-to-many).
            'doctors'        => $this->whenLoaded('doctors', fn () => $this->doctors->map(fn ($d) => [
                'id'   => $d->id,
                'name' => $d->name,
            ])->values()),
            'doctor_ids'     => $this->whenLoaded('doctors', fn () => $this->doctors->pluck('id')->values()),
            'name'               => $this->name,
            'description'        => $this->description,
            'price'              => (float) $this->price,
            'price_from'         => (bool) $this->price_from,
            'price_includes'     => $this->price_includes,
            'price_excludes'     => $this->price_excludes,
            'image'              => $this->image,
            // Inline discounted price (the service's own offer), when set. Drives
            // the form prefill and the strikethrough price on public cards.
            'offer_price'        => $this->whenLoaded('inlineOffer', fn () => $this->inlineOffer ? (float) $this->inlineOffer->price : null),
            'offer_ends_at'      => $this->whenLoaded('inlineOffer', fn () => $this->inlineOffer?->ends_at?->toDateString()),
            'is_active'          => (bool) $this->is_active,
            // System-managed "خدمات أخرى" catch-all: shown locked (no edit/delete).
            'is_catchall'        => (bool) $this->is_catchall,
            // Moderation gate: 'approved' shows publicly; 'pending' is hidden
            // until an admin approves the catalog request it belongs to.
            'approval_status'    => $this->approval_status,
            'catalog_service_id' => $this->catalog_service_id,
            'catalog_service'    => $this->whenLoaded('catalogService', fn () => $this->catalogService ? [
                'id'     => $this->catalogService->id,
                'name'   => $this->catalogService->name,
                'status' => $this->catalogService->status,
            ] : null),
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
