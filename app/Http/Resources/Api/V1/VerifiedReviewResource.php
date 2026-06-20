<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A verified review as the CLINIC sees it — for the reply surface. The
 * review text/ratings are READ-ONLY here; the clinic can only add/edit a
 * public reply (non-coercive: it never alters or hides the review).
 */
class VerifiedReviewResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'reference_code' => $this->reference_code,
            'clinic_rating'  => $this->clinic_rating,
            'doctor_rating'  => $this->doctor_rating,
            'doctor'         => $this->whenLoaded('doctor', fn () => $this->doctor ? [
                'id'   => $this->doctor->id,
                'name' => $this->doctor->name,
            ] : null),
            'comment'        => $this->comment,
            'customer_name'  => $this->customer_name,
            'status'         => $this->status,
            'is_visible'     => (bool) $this->is_visible,
            'submitted_at'   => $this->submitted_at?->toIso8601String(),
            'reply'          => $this->clinic_reply_text ? [
                'text'    => $this->clinic_reply_text,
                'by_name' => $this->clinic_replied_by_name_snapshot,
                'by_role' => $this->clinic_replied_by_role_snapshot,
                'at'      => $this->clinic_replied_at?->toIso8601String(),
            ] : null,
            // The clinic's own report status (so the UI shows "under review").
            'report'         => $this->reported_at ? [
                'reason'      => $this->report_reason,
                'at'          => $this->reported_at->toIso8601String(),
                'decided'     => $this->moderated_at !== null,
                'action'      => $this->moderation_action,
            ] : null,
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
