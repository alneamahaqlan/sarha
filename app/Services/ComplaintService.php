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

    /**
     * Admin replies directly to the customer who filed the complaint. The
     * reply is shown on the customer's /account/complaints page, and a
     * platform notification is queued for the customer's bell.
     */
    public function replyToCustomer(Complaint $complaint, string $text, int $adminId): Complaint
    {
        return DB::transaction(function () use ($complaint, $text, $adminId) {
            $complaint->update([
                'admin_reply_text'          => $text,
                'admin_replied_at'          => now(),
                'admin_replied_by_admin_id' => $adminId,
            ]);

            if ($complaint->user_id) {
                \App\Models\PlatformNotification::create([
                    'notifiable_type' => \App\Models\User::class,
                    'notifiable_id'   => $complaint->user_id,
                    'type'            => 'complaint.admin_reply',
                    'icon'            => 'heroicon-o-chat-bubble-left-right',
                    'url'             => '/account/complaints',
                    'priority'        => 'normal',
                    'title'           => 'رد على شكواك',
                    'body'            => $complaint->subject,
                    'data'            => ['complaint_id' => $complaint->id],
                ]);
            }

            return $complaint->fresh();
        });
    }

    /** Reopen a resolved/rejected complaint back to in_review. */
    public function reopen(Complaint $complaint): Complaint
    {
        $complaint->update([
            'status'      => 'in_review',
            'resolved_at' => null,
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
