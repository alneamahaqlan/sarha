<?php

namespace App\Http\Resources\Api\V1;

use App\Support\ActingClinicUser;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerNoteResource extends JsonResource
{
    public function toArray($request): array
    {
        // Whether the current actor can edit/delete this note. Author
        // owns their notes; owner can manage any.
        $isAuthor = $this->created_by_type === ActingClinicUser::actorType()
            && (int) $this->created_by_id === (int) ActingClinicUser::actorId();
        $isManager = ActingClinicUser::can('customers.notes.manage');

        return [
            'id'              => $this->id,
            'customer_id'     => $this->customer_id,
            'body'            => $this->body,
            'is_pinned'       => $this->is_pinned,
            'created_by_name' => $this->created_by_name,
            'created_by_role' => $this->created_by_type ? class_basename($this->created_by_type) : null,
            'is_author'       => $isAuthor,
            'can_edit'        => $isAuthor || $isManager,
            'can_delete'      => $isAuthor || $isManager,
            'can_pin'         => $isAuthor || $isManager,
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
