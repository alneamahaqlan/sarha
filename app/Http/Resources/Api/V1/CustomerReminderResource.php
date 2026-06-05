<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerReminderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'customer_id'     => $this->customer_id,
            'customer_name'   => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'customer_phone'  => $this->whenLoaded('customer', fn () => $this->customer?->phone),
            'booking_id'      => $this->booking_id,
            'assignee_member_id' => $this->assignee_member_id,
            'assignee_name'      => $this->whenLoaded('assigneeMember', fn () => $this->assigneeMember?->name),
            'remind_at'       => $this->remind_at?->toIso8601String(),
            'note'            => $this->note,
            'status'          => $this->status,
            'is_overdue'      => $this->is_overdue,
            'created_by_name' => $this->created_by_name,
            'notified_at'     => $this->notified_at?->toIso8601String(),
            'completed_at'    => $this->completed_at?->toIso8601String(),
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
