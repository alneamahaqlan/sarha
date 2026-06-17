<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Clinic\ReplyToReviewRequest;
use App\Http\Requests\Api\V1\Clinic\ReportReviewRequest;
use App\Http\Resources\Api\V1\VerifiedReviewResource;
use App\Models\ClinicTeamMember;
use App\Models\VerifiedReview;
use App\Services\ClinicActivityLogger;
use App\Support\ActingClinicUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The clinic's verified reviews + its public reply. Reviews are read-only
 * here — the clinic can only ADD/EDIT a reply, never alter or hide the
 * review (non-coercive). Isolation via query filter + VerifiedReviewPolicy.
 */
class ClinicVerifiedReviewController extends Controller
{
    public function __construct(private readonly ClinicActivityLogger $activity) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VerifiedReview::class);

        $query = VerifiedReview::query()
            ->where('clinic_id', auth('clinic')->id())
            ->where('status', VerifiedReview::STATUS_PUBLISHED)
            ->with('doctor:id,name')
            ->latest('submitted_at');

        if ($rating = (int) $request->input('rating')) {
            $query->where('clinic_rating', $rating);
        }
        if ($request->input('replied') === 'yes') {
            $query->whereNotNull('clinic_reply_text');
        } elseif ($request->input('replied') === 'no') {
            $query->whereNull('clinic_reply_text');
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        return VerifiedReviewResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function reply(ReplyToReviewRequest $request, VerifiedReview $review): JsonResponse
    {
        $this->authorize('reply', $review);

        $actor = ActingClinicUser::actor();

        $review->update([
            'clinic_reply_text'               => $request->validated()['reply'],
            // Owner replies leave the FK null; the name snapshot carries the
            // identity so the public string composes cleanly (mirrors Complaint).
            'clinic_replied_by_member_id'     => $actor instanceof ClinicTeamMember ? $actor->id : null,
            'clinic_replied_by_name_snapshot' => ActingClinicUser::actorName(),
            'clinic_replied_by_role_snapshot' => ActingClinicUser::role()->value,
            'clinic_replied_at'               => now(),
        ]);

        $this->activity->log('review.replied', $review, [
            'reference' => $review->reference_code,
            'customer'  => $review->customer_name,
        ]);

        return response()->json([
            'data' => (new VerifiedReviewResource($review->fresh()->load('doctor:id,name')))->resolve(),
        ]);
    }

    /**
     * Flag a review as spam/abuse for admin review. This does NOT hide the
     * review — it stays public until an admin confirms. A negative review
     * is not a reportable reason (enforced by the allowed reasons).
     */
    public function report(ReportReviewRequest $request, VerifiedReview $review): JsonResponse
    {
        $this->authorize('report', $review);

        $data = $request->validated();
        $review->update([
            'reported_at'      => now(),
            'report_reason'    => $data['reason'],
            'report_note'      => $data['note'] ?? null,
            'reported_by_name' => ActingClinicUser::actorName(),
            // is_visible deliberately untouched — reporting never hides.
        ]);

        $this->activity->log('review.reported', $review, [
            'reference' => $review->reference_code,
            'reason'    => $data['reason'],
        ]);

        return response()->json([
            'data' => (new VerifiedReviewResource($review->fresh()->load('doctor:id,name')))->resolve(),
        ]);
    }
}
