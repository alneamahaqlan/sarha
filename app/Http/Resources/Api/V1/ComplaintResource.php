<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'reference_code'    => $this->reference_code,
            'clinic_id'         => $this->clinic_id,
            'user_id'           => $this->user_id,
            'booking_id'        => $this->booking_id,
            'source'            => $this->source,
            'customer_name'     => $this->customer_name,
            'customer_phone'    => $this->customer_phone,
            'customer_email'    => $this->customer_email,
            'type'              => $this->type,
            'status'            => $this->status,
            'priority'          => $this->priority,
            'subject'           => $this->subject,
            'description'       => $this->description,
            'admin_notes'       => $this->admin_notes,
            'resolution'        => $this->resolution,
            'assigned_admin_id' => $this->assigned_admin_id,
            'resolved_at'       => $this->resolved_at?->toIso8601String(),
            'clinic_notified'   => (bool) $this->clinic_notified,
            'clinic'            => $this->whenLoaded('clinic', fn() => $this->clinic ? [
                'id'   => $this->clinic->id,
                'name' => $this->clinic->name,
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
