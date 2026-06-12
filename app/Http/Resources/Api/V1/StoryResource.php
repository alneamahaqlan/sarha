<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class StoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'clinic_id'  => $this->clinic_id,
            'image'      => $this->image,
            'image_url'  => $this->image ? Storage::url($this->image) : null,
            'caption'    => $this->caption,
            'views_count' => (int) $this->views_count,
            'ends_at'    => $this->ends_at,
            'is_active'  => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
