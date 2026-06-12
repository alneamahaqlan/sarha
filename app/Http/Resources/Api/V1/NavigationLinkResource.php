<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class NavigationLinkResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'location'       => $this->location,
            'footer_column'  => $this->footer_column,
            'label_ar'       => $this->label_ar,
            'label_en'       => $this->label_en,
            'url'            => $this->url,
            'static_page_id' => $this->static_page_id,
            'route_name'     => $this->route_name,
            'open_new_tab'   => (bool) $this->open_new_tab,
            'is_active'      => (bool) $this->is_active,
            'sort_order'     => (int) $this->sort_order,
            'resolved_url'   => $this->resolved_url,
            'static_page'    => $this->whenLoaded('staticPage', fn () => [
                'id'       => $this->staticPage->id,
                'slug'     => $this->staticPage->slug,
                'title_ar' => $this->staticPage->title_ar,
            ]),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
