<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ClinicReportResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'reference_code' => $this->reference_code,
            'clinic'         => $this->whenLoaded('clinic', fn () => [
                'id'   => $this->clinic->id,
                'name' => $this->clinic->name,
            ]),
            'type'           => $this->type,
            'priority'       => $this->priority,
            'status'         => $this->status,
            'subject'        => $this->subject,
            'description'    => $this->description,
            'admin_notes'    => $this->admin_notes,
            'resolution'     => $this->resolution,
            'resolved_at'    => $this->resolved_at?->toIso8601String(),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
