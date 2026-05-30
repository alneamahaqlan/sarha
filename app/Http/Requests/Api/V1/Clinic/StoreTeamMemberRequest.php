<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Enums\ClinicRole;
use App\Models\Clinic;
use App\Models\ClinicTeamMember;
use App\Support\ActingClinicUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamMemberRequest extends FormRequest
{
    /**
     * Only the owner can create team members. Coordinator + Reception
     * fail this check because their permission set does not include
     * `team.manage`.
     */
    public function authorize(): bool
    {
        return ActingClinicUser::can('team.manage');
    }

    public function rules(): array
    {
        $clinicId = ActingClinicUser::clinicId();

        return [
            'name'  => ['required', 'string', 'max:120'],
            'phone' => [
                'required', 'string', 'max:32',
                // Saudi-style mobile (matches existing booking + clinic
                // validators) — kept loose enough that a future +9665…
                // E.164 form would also pass.
                'regex:/^0\d{9}$|^\+\d{7,15}$/',
                // Per-clinic uniqueness among active (non-soft-deleted)
                // members. We don't use a DB unique index because the
                // soft-deleted rows would block re-adding a member with
                // the same phone after their old row was deleted.
                Rule::unique('clinic_team_members', 'phone')
                    ->where('clinic_id', $clinicId)
                    ->whereNull('deleted_at'),
                // Cross-table guard: a member's phone cannot collide
                // with another clinic's owner phone (would make login
                // ambiguous — owner lookup wins first).
                Rule::notIn(Clinic::where('phone', $this->input('phone'))->pluck('phone')->all()),
            ],
            'role'  => ['required', Rule::in([ClinicRole::COORDINATOR->value, ClinicRole::RECEPTION->value])],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => __('admin.team.phone_taken'),
            'phone.not_in' => __('admin.team.phone_in_use_by_owner'),
            'role.in'      => __('admin.team.role_invalid'),
        ];
    }
}
