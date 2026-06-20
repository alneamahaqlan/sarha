<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'reference_code' => $this->reference_code,
            'clinic_id'      => $this->clinic_id,
            'user_id'        => $this->user_id,
            'service_id'     => $this->service_id,
            'customer_name'  => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'notes'          => $this->notes,
            'clinic_notes'   => $this->clinic_notes,
            'status'         => $this->status,
            'appointment_at' => $this->appointment_at?->toIso8601String(),
            // Attendance — an independent signal, separate from `status`.
            'attended_at'    => $this->attended_at?->toIso8601String(),
            'is_attended'    => ! is_null($this->attended_at),
            'attended_by'    => $this->attended_at ? [
                'type' => $this->attended_by_type ? class_basename($this->attended_by_type) : null,
                'id'   => $this->attended_by_id,
                'name' => $this->attended_by_name,
            ] : null,
            'source'         => $this->source,
            'clinic'         => $this->whenLoaded('clinic', fn() => [
                'id'   => $this->clinic->id,
                'name' => $this->clinic->name,
            ]),
            'service'        => $this->whenLoaded('service', fn() => $this->service ? [
                'id'   => $this->service->id,
                'name' => $this->service->name,
            ] : null),
            // By-proxy context for the clinic admin view: when the
            // booking was placed by an account holder on behalf of one
            // of their saved relatives, surface both the relative
            // (= the patient) and the booker (= the contact account).
            'is_for_relative' => ! is_null($this->relative_id),
            'relative'        => $this->whenLoaded('relative', fn() => $this->relative ? [
                'id'                  => $this->relative->id,
                'name'                => $this->relative->name,
                'relationship_type'   => $this->relative->relationship_type,
                'relationship_label'  => $this->relative->relationship_label,
                'phone'               => $this->relative->phone,
            ] : null),
            'booker'          => $this->whenLoaded('booker', fn() => $this->booker ? [
                'id'    => $this->booker->id,
                'name'  => $this->booker->name,
                'phone' => $this->booker->phone,
            ] : null),
            'is_trashed'     => $this->trashed(),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
            'deleted_at'     => $this->deleted_at?->toIso8601String(),
        ];
    }
}
