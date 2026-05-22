<?php

namespace App\Services;

use App\Models\Complaint;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for Complaint state-transition actions.
 *
 * Extracted verbatim from the inline closures in Filament's
 * ComplaintResource so that BOTH the Filament panel and the React/API layer
 * call this exact code. No business-logic change.
 */
class ComplaintService
{
    public function markInReview(Complaint $complaint): Complaint
    {
        // Mirrors ComplaintResource Action 'mark_in_review' (visible when status==='new').
        $complaint->update(['status' => 'in_review']);
        return $complaint->fresh();
    }

    public function resolve(Complaint $complaint, string $resolution): Complaint
    {
        // Mirrors ComplaintResource Action 'resolve' (visible when status in [new, in_review]).
        $complaint->update([
            'status'      => 'resolved',
            'resolution'  => $resolution,
            'resolved_at' => now(),
        ]);
        return $complaint->fresh();
    }

    public function reject(Complaint $complaint, string $reason): Complaint
    {
        // Mirrors ComplaintResource Action 'reject' (visible when status in [new, in_review]).
        $complaint->update([
            'status'      => 'rejected',
            'admin_notes' => $reason,
            'resolved_at' => now(),
        ]);
        return $complaint->fresh();
    }

    public function notifyClinic(Complaint $complaint): Complaint
    {
        // Mirrors ComplaintResource Action 'notify_clinic' (visible when clinic_id && !clinic_notified).
        // Wrapped in transaction in case the NotificationService side-effect grows.
        return DB::transaction(function () use ($complaint) {
            $complaint->update(['clinic_notified' => true]);
            return $complaint->fresh();
        });
    }
}
