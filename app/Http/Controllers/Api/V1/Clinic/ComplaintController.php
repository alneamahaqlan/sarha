<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ComplaintResource;
use App\Models\Clinic;
use App\Models\Complaint;
use App\Models\ClinicTeamMember;
use App\Services\ClinicActivityLogger;
use App\Support\ActingClinicUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Customer complaints raised AGAINST the complex. Read-only listing +
 * a new "reply" endpoint that lets the complex respond once per
 * complaint, attributing the reply to the specific team member who
 * authored it (per spec — the customer sees the member's name).
 */
class ComplaintController extends Controller
{
    public function __construct(
        private readonly ClinicActivityLogger $activity,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $clinicId = ActingClinicUser::clinicId();

        $query = Complaint::query()
            ->where('clinic_id', $clinicId)
            // Hide the (now-deprecated) 'admin'-source rows from the
            // complex's view so it only sees what customers raised.
            ->where('source', 'customer');

        if ($status = $request->string('filter.status')->toString()) {
            $query->where('status', $status);
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return ComplaintResource::collection($query->paginate($perPage)->withQueryString());
    }

    /**
     * Single-reply per complaint (overwrites existing). Stamps the
     * member-id + name + role snapshot so the customer-facing view
     * + activity log can render the actor even after the member is
     * soft-deleted.
     */
    public function reply(Request $request, Complaint $complaint): JsonResponse
    {
        abort_if($complaint->clinic_id !== ActingClinicUser::clinicId(), 404);
        abort_if($complaint->source !== 'customer', 403);

        $data = $request->validate([
            'reply' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $actor = ActingClinicUser::actor();
        $role  = ActingClinicUser::role();

        $complaint->update([
            'clinic_reply_text' => $data['reply'],
            // Snapshot the member identity — owner replies leave the
            // FK null and the name snapshot carries the clinic name
            // so the customer-facing string still composes cleanly.
            'clinic_replied_by_member_id'      => $actor instanceof ClinicTeamMember ? $actor->id : null,
            'clinic_replied_by_name_snapshot'  => ActingClinicUser::actorName(),
            'clinic_replied_by_role_snapshot'  => $role->value,
            'clinic_replied_at'                => now(),
        ]);

        $this->activity->log('complaint.replied', $complaint, [
            'reference' => $complaint->reference_code,
            'customer'  => $complaint->customer_name,
        ]);

        return response()->json(['data' => (new ComplaintResource($complaint->fresh()))->resolve()]);
    }
}
