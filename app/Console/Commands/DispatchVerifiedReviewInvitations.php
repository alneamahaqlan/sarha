<?php

namespace App\Console\Commands;

use App\Enums\NotificationEvent;
use App\Models\VerifiedReview;
use App\Services\NotificationDispatcher;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

/**
 * Sends the post-visit review invitation for eligible (pending) reviews
 * whose visit was at least `--hours-after` ago — DEFERRED from the
 * attendance moment so the patient has actually finished the service.
 *
 * Account holders get the in-app REVIEW_INVITATION (deep-links to the
 * review form); account-less patients get an SMS with a SIGNED submission
 * link (possession of the link is the authorization, consistent with the
 * reward-transfer SMS). Idempotent: only picks up invited_at IS NULL and
 * stamps it, so re-runs never double-invite. Scheduled from bootstrap.
 */
class DispatchVerifiedReviewInvitations extends Command
{
    protected $signature = 'saerha:dispatch-review-invitations {--hours-after=24 : Invite once the visit is at least this many hours old} {--dry-run}';
    protected $description = 'Invite patients to leave a verified review a while after their confirmed visit.';

    public function handle(NotificationDispatcher $dispatcher, SmsService $sms): int
    {
        $hours = max(1, (int) $this->option('hours-after'));
        $cutoff = now()->subHours($hours);

        $due = VerifiedReview::query()
            ->where('status', VerifiedReview::STATUS_PENDING)
            ->whereNull('invited_at')
            ->whereHas('booking', fn ($q) => $q->whereNotNull('attended_at')->where('attended_at', '<=', $cutoff))
            ->with(['user', 'clinic:id,name'])
            ->limit(500)
            ->get();

        if ($this->option('dry-run')) {
            $this->warn("DRY-RUN: {$due->count()} review invitation(s) due — none sent.");
            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($due as $review) {
            $clinicName = $review->clinic?->name;

            if ($review->user) {
                $notification = $dispatcher->dispatch(NotificationEvent::REVIEW_INVITATION, $review->user, [
                    'clinic'    => $clinicName,
                    'review_id' => $review->id,
                ]);
                if ($notification !== null) {
                    $sent++;
                }
            } elseif ($review->customer_phone) {
                // No account — SMS a signed link (30-day window) to submit.
                try {
                    $link = URL::temporarySignedRoute('review.form', now()->addDays(30), ['review' => $review->id]);
                    $sms->send($review->customer_phone, __('site.review_invite_sms', ['clinic' => $clinicName ?? '', 'link' => $link]));
                    $sent++;
                } catch (\Throwable $e) {
                    // logged by SmsService; stamp anyway below to avoid retry storms
                }
            }

            $review->forceFill(['invited_at' => now()])->save();
        }

        $this->info("Review invitations dispatched: {$sent} / {$due->count()} due.");

        return self::SUCCESS;
    }
}
