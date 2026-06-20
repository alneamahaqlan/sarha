<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Clinic;
use App\Models\VerifiedReview;

/**
 * Clinic isolation for verified reviews: a clinic may only see/reply to
 * reviews left for IT. Admins (active) have full access for moderation.
 * The customer authoring side is scoped to their own identity on the
 * account/public layer, not through this policy.
 *
 * Note: there is no "hide" ability for clinics — replying is public and a
 * genuine negative can never be buried by the clinic (only an admin may
 * moderate spam/abuse via is_visible). That invariant lives in the
 * controllers/service, not here.
 */
class VerifiedReviewPolicy
{
    public function viewAny(Admin|Clinic $actor): bool
    {
        return $actor instanceof Admin ? $actor->is_active : true;
    }

    public function view(Admin|Clinic $actor, VerifiedReview $review): bool
    {
        if ($actor instanceof Admin) {
            return $actor->is_active;
        }
        return $actor->id === $review->clinic_id;
    }

    /** Posting/editing the public clinic reply. */
    public function reply(Admin|Clinic $actor, VerifiedReview $review): bool
    {
        if ($actor instanceof Admin) {
            return $actor->is_active;
        }
        return $actor->id === $review->clinic_id;
    }

    /** Flagging a review as spam/abuse for admin review (clinic owns it). */
    public function report(Admin|Clinic $actor, VerifiedReview $review): bool
    {
        if ($actor instanceof Admin) {
            return $actor->is_active;
        }
        return $actor->id === $review->clinic_id;
    }

    /** Admin-only moderation (hide spam/abuse with a reason, or dismiss). */
    public function moderate(Admin $actor, VerifiedReview $review): bool
    {
        return $actor->is_active;
    }
}
