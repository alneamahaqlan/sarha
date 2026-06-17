<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A reported verified review as the ADMIN moderation queue sees it: the
 * review content + which clinic + the clinic's report + any moderation
 * decision (audit). The review text is read-only; the admin only decides
 * visibility.
 */
class AdminVerifiedReviewResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'reference_code' => $this->reference_code,
            'clinic'         => $this->whenLoaded('clinic', fn () => $this->clinic ? [
                'id'   => $this->clinic->id,
                'name' => $this->clinic->name,
                'slug' => $this->clinic->slug,
            ] : null),
            'clinic_rating'  => $this->clinic_rating,
            'doctor_rating'  => $this->doctor_rating,
            'comment'        => $this->comment,
            'customer_name'  => $this->customer_name,
            'is_visible'     => (bool) $this->is_visible,
            'submitted_at'   => $this->submitted_at?->toIso8601String(),
            'report'         => $this->reported_at ? [
                'reason'   => $this->report_reason,
                'note'     => $this->report_note,
                'by_name'  => $this->reported_by_name,
                'at'       => $this->reported_at->toIso8601String(),
            ] : null,
            'moderation'     => $this->moderated_at ? [
                'action'   => $this->moderation_action,
                'reason'   => $this->moderation_reason,
                'at'       => $this->moderated_at->toIso8601String(),
                'by'       => $this->whenLoaded('moderatedByAdmin', fn () => $this->moderatedByAdmin?->name),
            ] : null,
        ];
    }
}
