<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\StoreTeamMemberRequest;
use App\Http\Requests\Api\V1\Clinic\UpdateTeamMemberRequest;
use App\Http\Resources\Api\V1\ClinicTeamMemberResource;
use App\Models\ClinicTeamMember;
use App\Services\ClinicActivityLogger;
use App\Support\ActingClinicUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/**
 * Owner-only CRUD for clinic team members. The middleware
 * `clinic.role:team.manage` enforces this at the route layer; the
 * authorize() guards on the FormRequests double-check at runtime.
 *
 * The store / regeneratePassword actions return the freshly-generated
 * temp password ONCE so the React layer can show the "copy to share"
 * dialog per spec. Subsequent reads never include the password.
 */
class TeamController extends Controller
{
    public function __construct(
        private readonly ClinicActivityLogger $activity,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $members = ClinicTeamMember::query()
            ->where('clinic_id', ActingClinicUser::clinicId())
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%' . $request->input('q') . '%';
                $q->where(fn ($qq) => $qq->where('name', 'like', $like)->orWhere('phone', 'like', $like));
            })
            ->orderByDesc('created_at')
            ->get();

        return ClinicTeamMemberResource::collection($members);
    }

    public function store(StoreTeamMemberRequest $request): JsonResponse
    {
        $tempPassword = $this->generateTempPassword();

        $member = ClinicTeamMember::create([
            'clinic_id'     => ActingClinicUser::clinicId(),
            'name'          => $request->string('name'),
            'phone'         => $request->string('phone'),
            'role'          => $request->string('role'),
            'password'      => $tempPassword, // hashed by cast
            'is_active'     => true,
        ]);

        $this->activity->log('team.member_added', $member, [
            'member_name' => $member->name,
            'member_role' => $member->role,
        ]);

        return response()->json([
            'data' => (new ClinicTeamMemberResource($member))->resolve(),
            // Returned ONCE; the React layer shows it in a one-time
            // reveal dialog so the owner can hand it to the new member.
            'temp_password' => $tempPassword,
        ], 201);
    }

    public function update(UpdateTeamMemberRequest $request, ClinicTeamMember $member): ClinicTeamMemberResource
    {
        $this->assertOwnsMember($member);

        $before = ['role' => $member->role, 'is_active' => $member->is_active];
        $member->fill($request->validated())->save();

        $changedActive = $before['is_active'] !== (bool) $member->is_active;
        $changedRole   = $before['role']      !== $member->role;

        if ($changedActive) {
            $this->activity->log(
                $member->is_active ? 'team.member_enabled' : 'team.member_disabled',
                $member,
                ['member_name' => $member->name],
            );
        }
        if ($changedRole) {
            $this->activity->log('team.member_role_changed', $member, [
                'member_name' => $member->name,
                'from' => $before['role'],
                'to'   => $member->role,
            ]);
        }

        return new ClinicTeamMemberResource($member->fresh());
    }

    public function destroy(ClinicTeamMember $member): JsonResponse
    {
        $this->assertOwnsMember($member);

        $name = $member->name;
        $member->delete(); // soft-delete — history rows survive

        $this->activity->log('team.member_removed', $member, ['member_name' => $name]);

        return response()->json(['message' => __('admin.team.removed_ok')]);
    }

    public function regeneratePassword(ClinicTeamMember $member): JsonResponse
    {
        $this->assertOwnsMember($member);

        $tempPassword = $this->generateTempPassword();
        $member->forceFill(['password' => $tempPassword])->save();

        $this->activity->log('team.member_password_reset', $member, [
            'member_name' => $member->name,
        ]);

        return response()->json([
            'data' => (new ClinicTeamMemberResource($member->fresh()))->resolve(),
            'temp_password' => $tempPassword,
        ]);
    }

    /**
     * 404 if the route-bound member doesn't belong to the acting
     * clinic. Mirrors the abort_if pattern used across other clinic
     * controllers (e.g. DoctorController).
     */
    private function assertOwnsMember(ClinicTeamMember $member): void
    {
        abort_if($member->clinic_id !== ActingClinicUser::clinicId(), 404);
    }

    /**
     * 10-character mixed-case alphanumeric. Strong enough for an
     * initial handoff; spec says the member is expected to change it
     * on first login.
     */
    private function generateTempPassword(): string
    {
        return Str::password(length: 10, letters: true, numbers: true, symbols: false);
    }
}
