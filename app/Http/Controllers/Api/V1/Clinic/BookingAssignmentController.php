<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\ClinicTeamMember;
use App\Services\ClinicActivityLogger;
use App\Support\ActingClinicUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Polymorphic assignee on a booking. Owner can assign anyone in the
 * clinic; coordinator/reception can only self-assign or unassign.
 *
 * Payload: { assignee_type: "Clinic"|"ClinicTeamMember"|null,
 *            assignee_id: int|null }
 * Null on either field = unassign.
 */
class BookingAssignmentController extends Controller
{
    public function __construct(
        private readonly ClinicActivityLogger $activity,
    ) {}

    public function update(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        $validated = $request->validate([
            'assignee_type' => ['nullable', Rule::in(['Clinic', 'ClinicTeamMember'])],
            'assignee_id'   => ['nullable', 'integer'],
        ]);

        $type = $validated['assignee_type'] ?? null;
        $id   = $validated['assignee_id'] ?? null;
        $unassigning = is_null($type) || is_null($id);

        if (! $unassigning) {
            $fqType = $type === 'Clinic' ? Clinic::class : ClinicTeamMember::class;

            // Owner can assign anyone. Non-owners can only assign
            // themselves (matching their actor type+id exactly).
            $role = ActingClinicUser::role();
            if (! $role->can('team.manage')) {
                $actorType = ActingClinicUser::actorType();
                $actorId   = ActingClinicUser::actorId();
                if ($fqType !== $actorType || (int) $id !== (int) $actorId) {
                    return response()->json(['message' => 'forbidden_self_only'], 403);
                }
            }

            // Verify the target belongs to this clinic.
            if ($fqType === Clinic::class) {
                if ((int) $id !== (int) $booking->clinic_id) {
                    return response()->json(['message' => 'invalid_assignee'], 422);
                }
            } else {
                $exists = ClinicTeamMember::where('id', $id)
                    ->where('clinic_id', $booking->clinic_id)
                    ->where('is_active', true)
                    ->exists();
                if (! $exists) {
                    return response()->json(['message' => 'invalid_assignee'], 422);
                }
            }

            $booking->update([
                'assignee_type' => $fqType,
                'assignee_id'   => (int) $id,
            ]);

            $assignee = $booking->fresh()->assignee;
            $this->activity->log('booking.assigned', $booking, [
                'reference' => $booking->reference_code,
                'to'        => $assignee->name ?? '—',
                'role'      => $assignee instanceof ClinicTeamMember ? $assignee->role : 'owner',
            ]);
        } else {
            $previous = $booking->assignee?->name;
            $booking->update(['assignee_type' => null, 'assignee_id' => null]);
            $this->activity->log('booking.unassigned', $booking, [
                'reference' => $booking->reference_code,
                'from'      => $previous,
            ]);
        }

        return response()->json([
            'data' => [
                'assignee_type' => $booking->assignee_type ? class_basename($booking->assignee_type) : null,
                'assignee_id'   => $booking->assignee_id,
                'name'          => $booking->assignee?->name,
            ],
        ]);
    }

    /**
     * Lists assignable identities for the picker: the clinic owner +
     * every active team member. The clinic guard already binds the
     * current Clinic so we never expose other clinics' members.
     */
    public function index(): JsonResponse
    {
        $clinicId = (int) auth('clinic')->id();
        $clinic   = Clinic::find($clinicId);

        $members = ClinicTeamMember::where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $payload = [];
        if ($clinic) {
            $payload[] = [
                'type' => 'Clinic',
                'id'   => $clinic->id,
                'name' => $clinic->name,
                'role' => 'owner',
            ];
        }
        foreach ($members as $m) {
            $payload[] = [
                'type' => 'ClinicTeamMember',
                'id'   => $m->id,
                'name' => $m->name,
                'role' => $m->role,
            ];
        }

        return response()->json(['data' => $payload]);
    }
}
