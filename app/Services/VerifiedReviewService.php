<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\VerifiedReview;
use App\Support\PhoneNormalizer;

/**
 * Owns the verified-review lifecycle: eligibility (created on confirmed
 * attendance), its symmetric removal (on revoked attendance, if still
 * unsubmitted), and the patient's verified submission.
 *
 * Failures in eligibility/removal must never break the attendance action
 * — the listeners wrap calls and this service stays side-effect-light.
 */
class VerifiedReviewService
{
    /**
     * Create the pending review row for an attended booking (the patient
     * becomes eligible to review that visit). Idempotent via the unique
     * booking_id — a re-confirm after a revoke-delete mints a fresh one.
     * Returns null when the booking isn't attended (defensive).
     */
    public function createEligibility(Booking $booking): ?VerifiedReview
    {
        if (! $booking->attended_at) {
            return null;
        }

        return VerifiedReview::firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'clinic_id'      => $booking->clinic_id,
                'customer_id'    => $booking->customer_id,
                'user_id'        => $booking->user_id,
                'customer_name'  => $booking->customer_name,
                // Normalized so ownership matching (account phone vs review
                // phone) is reliable regardless of input format.
                'customer_phone' => PhoneNormalizer::normalizeOrSelf($booking->customer_phone) ?? $booking->customer_phone,
                'status'         => VerifiedReview::STATUS_PENDING,
            ],
        );
    }

    /**
     * Symmetric counterpart of createEligibility: when attendance is
     * revoked, drop the PENDING review for that booking so a mis-click
     * doesn't leave a review invitation for a visit that didn't happen.
     * A review the patient already SUBMITTED (published) is kept — same
     * rule as a redeemed cashback voucher surviving a revoke.
     */
    public function deletePendingFor(Booking $booking): int
    {
        return VerifiedReview::where('booking_id', $booking->id)
            ->where('status', VerifiedReview::STATUS_PENDING)
            ->delete();
    }

    /**
     * Apply a patient's verified submission. The CALLER must already have
     * authorized ownership (account identity / signed link). This enforces
     * the domain invariants: the booking is still attended, the review is
     * still pending (one submission per booking), then publishes it.
     * Non-coercive: whatever the patient rated is published as-is.
     *
     * @param  array{clinic_rating:int, doctor_rating?:?int, doctor_id?:?int, comment?:?string}  $data
     */
    public function submit(VerifiedReview $review, array $data): VerifiedReview
    {
        if ($review->status !== VerifiedReview::STATUS_PENDING) {
            throw new \RuntimeException('already_submitted');
        }
        if (! $review->booking?->attended_at) {
            throw new \RuntimeException('not_attended');
        }

        $review->update([
            'clinic_rating' => $data['clinic_rating'],
            'doctor_rating' => $data['doctor_rating'] ?? null,
            'doctor_id'     => $data['doctor_id'] ?? null,
            'comment'       => $data['comment'] ?? null,
            'status'        => VerifiedReview::STATUS_PUBLISHED,
            'submitted_at'  => now(),
        ]);

        return $review;
    }
}
