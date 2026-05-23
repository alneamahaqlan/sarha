<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class SalesLeadResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'clinic_name'       => $this->clinic_name,
            'contact_name'      => $this->contact_name,
            'phone'             => $this->phone,
            'email'             => $this->email,
            'license_number'    => $this->license_number,
            'city_id'           => $this->city_id,
            'district'          => $this->district,
            'address'           => $this->address,
            'status'            => $this->status,
            'assigned_to'       => $this->assigned_to,
            'next_follow_up_at' => $this->next_follow_up_at?->toIso8601String(),
            'last_contact_at'   => $this->last_contact_at?->toIso8601String(),
            'notes'             => $this->notes,
            'sales_notes'       => $this->sales_notes,
            'city'              => $this->whenLoaded('city', fn() => $this->city ? [
                'id'   => $this->city->id,
                'name' => $this->city->name,
            ] : null),
            'assigned_admin'    => $this->whenLoaded('assignedAdmin', fn() => $this->assignedAdmin ? [
                'id'   => $this->assignedAdmin->id,
                'name' => $this->assignedAdmin->name,
            ] : null),
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
