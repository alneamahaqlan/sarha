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
            'sub_clinic_id'    => $this->sub_clinic_id,
            'sub_clinic'       => $this->whenLoaded('subClinic', fn () => $this->subClinic ? [
                'id'   => $this->subClinic->id,
                'name' => $this->subClinic->name,
            ] : null),
            'name'             => $this->name,
            'specialty'        => $this->specialty,
            'gender'           => $this->gender,
            'photo'            => $this->photo,
            'photo_url'        => $this->photo_url,
            'bio'              => $this->bio,
            'qualifications'   => $this->qualifications,
            'years_experience' => $this->years_experience,
            'university'       => $this->university,
            'languages'        => $this->languages,
            'is_active'        => (bool) $this->is_active,
            'sort_order'       => (int) $this->sort_order,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
