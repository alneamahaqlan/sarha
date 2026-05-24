<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'clinic_id'        => $this->clinic_id,
            'name'             => $this->name,
            'specialty'        => $this->specialty,
            'photo'            => $this->photo,
            'photo_url'        => $this->photo_url,
            'bio'              => $this->bio,
            'years_experience' => $this->years_experience,
            'is_active'        => (bool) $this->is_active,
            'sort_order'       => (int) $this->sort_order,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
