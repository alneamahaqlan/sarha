<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\ClinicRole;
use App\Models\ClinicTeamMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->actor_role ? ClinicRole::tryFrom($this->actor_role) : null;

        return [
            'id'           => $this->id,
            'actor_id'     => $this->actor_id,
            'actor_type'   => $this->actor_type,
            'actor_name'   => $this->actor_name,
            'actor_role'   => $this->actor_role,
            'actor_color'  => $role?->colorToken(),
            // True if the actor was a team member that has since been
            // removed — the UI shows a "غير نشط" badge.
            'actor_removed'=> $this->actor_type === ClinicTeamMember::class
                ? optional($this->actor()->withTrashed()->first())->trashed() ?? false
                : false,
            'action'       => $this->action,
            'model_type'   => $this->model_type,
            'model_id'     => $this->model_id,
            'summary'      => $this->summary ?? [],
            'created_at'   => $this->created_at?->toIso8601String(),
        ];
    }
}
