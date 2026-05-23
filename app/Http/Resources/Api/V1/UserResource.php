<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'phone'          => $this->phone,
            'email'          => $this->email,
            'is_active'      => (bool) $this->is_active,
            'bookings_count' => $this->when(isset($this->bookings_count), (int) $this->bookings_count),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
