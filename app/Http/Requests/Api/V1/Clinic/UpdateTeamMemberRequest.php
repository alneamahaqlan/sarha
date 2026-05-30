<?php

namespace App\Http\Requests\Api\V1\Clinic;

use App\Enums\ClinicRole;
use App\Support\ActingClinicUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ActingClinicUser::can('team.manage');
    }

    public function rules(): array
    {
        // Edit can change name, role, or active state. Phone is
        // immutable (avoids re-collision logic + login surprise).
        return [
            'name'      => ['sometimes', 'required', 'string', 'max:120'],
            'role'      => ['sometimes', 'required', Rule::in([
                ClinicRole::COORDINATOR->value,
                ClinicRole::RECEPTION->value,
            ])],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
