<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\ClinicRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicTeamMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = ClinicRole::tryFrom($this->role);

        return [
            'id'             => $this->id,
            'clinic_id'      => $this->clinic_id,
            'name'           => $this->name,
            'phone'          => $this->phone,
            'role'           => $this->role,
            'role_color'     => $role?->colorToken(),
            'is_active'      => (bool) $this->is_active,
            'last_login_at'  => $this->last_login_at?->toIso8601String(),
            'created_at'     => $this->created_at?->toIso8601String(),
            // Soft-deleted members are only ever returned via
            // withTrashed queries; the flag lets the UI render the
            // "غير نشط" badge per spec.
            'deleted_at'     => $this->deleted_at?->toIso8601String(),
        ];
    }
}
