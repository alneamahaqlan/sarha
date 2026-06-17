<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ModerateReviewRequest;
use App\Http\Resources\Api\V1\AdminVerifiedReviewResource;
use App\Models\VerifiedReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

/**
 * Admin moderation of REPORTED verified reviews. The admin may hide a
 * review (is_visible=false) ONLY for spam/abuse, with a mandatory reason
 * and a full audit trail — a genuine negative is never grounds to hide.
 * Or dismiss the report (keep it public).
 */
class AdminVerifiedReviewController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->ensureAdmin();

        $query = VerifiedReview::query()
            ->with(['clinic:id,name,slug', 'moderatedByAdmin:id,name'])
            ->whereNotNull('reported_at')
            ->latest('reported_at');

        // Default to the pending queue; allow viewing decided ones too.
        $scope = $request->string('scope')->toString() ?: 'pending';
        if ($scope === 'pending') {
            $query->whereNull('moderated_at');
        } elseif ($scope === 'decided') {
            $query->whereNotNull('moderated_at');
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        return AdminVerifiedReviewResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function moderate(ModerateReviewRequest $request, VerifiedReview $review): JsonResponse
    {
        $admin = $this->ensureAdmin();

        $data = $request->validated();
        $hide = $data['action'] === 'hide';

        $review->update([
            'moderation_action'     => $hide ? VerifiedReview::MODERATION_HIDDEN : VerifiedReview::MODERATION_DISMISSED,
            'moderation_reason'     => $data['reason'] ?? null,
            'moderated_by_admin_id' => $admin->id,
            'moderated_at'          => now(),
            // Hide removes from public view; dismiss restores/keeps visible.
            'is_visible'            => ! $hide,
        ]);

        Log::info('verified_review.moderated', [
            'review'   => $review->id,
            'clinic'   => $review->clinic_id,
            'admin'    => $admin->id,
            'action'   => $review->moderation_action,
            'reason'   => $review->moderation_reason,
        ]);

        return response()->json([
            'data' => (new AdminVerifiedReviewResource($review->fresh()->load(['clinic:id,name,slug', 'moderatedByAdmin:id,name'])))->resolve(),
        ]);
    }

    private function ensureAdmin(): \App\Models\Admin
    {
        $admin = auth('admin')->user();
        abort_unless($admin && $admin->is_active, 403);
        return $admin;
    }
}
