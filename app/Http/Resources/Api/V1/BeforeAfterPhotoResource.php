<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeforeAfterPhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'clinic_id'     => $this->clinic_id,
            'sub_clinic_id' => $this->sub_clinic_id,
            'service_id'    => $this->service_id,
            'title'         => $this->title,
            'before_image'  => $this->before_image,
            'after_image'   => $this->after_image,
            'display_mode'  => $this->display_mode ?: 'side_by_side',
            'before_url'    => $this->before_url,
            'after_url'     => $this->after_url,
            'is_active'     => (bool) $this->is_active,
            'sort_order'    => (int) $this->sort_order,
            'sub_clinic'    => $this->whenLoaded('subClinic', fn () => $this->subClinic ? [
                'id'   => $this->subClinic->id,
                'name' => $this->subClinic->name,
            ] : null),
            'service'       => $this->whenLoaded('service', fn () => $this->service ? [
                'id'   => $this->service->id,
                'name' => $this->service->name,
            ] : null),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
