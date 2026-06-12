<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ClinicPageSection;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicPageSectionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'key'          => $this->key,
            'is_active'    => (bool) $this->is_active,
            'sort_order'   => (int) $this->sort_order,
            'title_ar'     => $this->title_ar,
            'title_en'     => $this->title_en,
            'item_limit'   => $this->item_limit !== null ? (int) $this->item_limit : null,
            // Free-form content bag (only the `faqs` section populates it).
            'content'      => $this->content,
            // Surface the policy bit to the UI so it can disable the
            // "hide" switch on protected rows without hard-coding the list
            // on the client side.
            'is_protected' => in_array($this->key, ClinicPageSection::PROTECTED_KEYS, true),
            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),
        ];
    }
}
