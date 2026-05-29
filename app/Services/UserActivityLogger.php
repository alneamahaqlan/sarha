<?php

namespace App\Services;

use App\Models\UserActivityEvent;
use App\Models\UserVisitSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point for writing user activity events. Wraps the
 * underlying table writes in try/catch so a logging failure NEVER
 * surfaces as a failed user request.
 *
 * Every public method takes either the request (so we can resolve
 * the auth user + IP + UA + current visit session) OR an explicit
 * user id when the caller already knows it (queued jobs, console).
 *
 * Spec rule respected: only meaningful events are recorded — the
 * caller decides which ones, this layer is a dumb writer.
 */
class UserActivityLogger
{
    public function logLogin(Request $request, int $userId): void
    {
        $this->write($request, $userId, UserActivityEvent::TYPE_LOGIN);
    }

    public function logLogout(Request $request, int $userId): void
    {
        $this->write($request, $userId, UserActivityEvent::TYPE_LOGOUT);
    }

    public function logOtpVerified(Request $request, int $userId, string $purpose): void
    {
        $this->write($request, $userId, UserActivityEvent::TYPE_OTP_VERIFIED, [
            'purpose' => $purpose,
        ]);
    }

    public function logSearch(Request $request, ?int $userId, string $query, ?int $cityId, ?int $categoryId, int $resultsCount): void
    {
        if (! $userId) return; // anonymous searches don't belong to anyone's profile
        $hasQuery = trim($query) !== '';
        // No keyword + at least one filter → record as filter (per
        // spec: "فلتر مدينة/تخصّص" is a distinct event type).
        $type = $hasQuery
            ? UserActivityEvent::TYPE_SEARCH
            : UserActivityEvent::TYPE_FILTER;

        $this->write($request, $userId, $type, [
            'q'              => $hasQuery ? trim($query) : null,
            'city_id'        => $cityId,
            'category_id'    => $categoryId,
            'results_count'  => $resultsCount,
        ]);
    }

    public function logClinicView(Request $request, ?int $userId, int $clinicId, string $slug): void
    {
        if (! $userId) return;
        $this->write($request, $userId, UserActivityEvent::TYPE_CLINIC_VIEW, [
            'slug' => $slug,
        ], relatedClinicId: $clinicId, relatedUrl: "/clinic/{$slug}");
    }

    public function logActionClick(Request $request, ?int $userId, int $clinicId, string $button): void
    {
        if (! $userId) return;
        $this->write($request, $userId, UserActivityEvent::TYPE_ACTION_CLICK, [
            'button' => $button,
        ], relatedClinicId: $clinicId);
    }

    public function logCompare(Request $request, ?int $userId, array $clinicIds): void
    {
        if (! $userId) return;
        $this->write($request, $userId, UserActivityEvent::TYPE_COMPARE, [
            'clinic_ids' => array_values(array_unique(array_map('intval', $clinicIds))),
        ]);
    }

    public function logAccountEdit(Request $request, int $userId, array $changes): void
    {
        if (empty($changes)) return;
        $this->write($request, $userId, UserActivityEvent::TYPE_ACCOUNT_EDIT, [
            'changes' => $changes,
        ]);
    }

    /**
     * Open (or extend) the current visit session for the user. Called
     * by the TrackUserVisit middleware on every authenticated request.
     * Returns the visit row so subsequent activity writes can stamp
     * `visit_session_id` for "expand session" UX.
     */
    public function touchVisitSession(Request $request, int $userId): ?UserVisitSession
    {
        try {
            $now = now();
            $idleCutoff = $now->copy()->subMinutes(UserVisitSession::IDLE_WINDOW_MINUTES);

            // Reuse the latest still-open session if it's within the
            // idle window — keeps "one row per visit" semantics.
            $current = UserVisitSession::query()
                ->where('user_id', $userId)
                ->whereNull('ended_at')
                ->where('last_activity_at', '>=', $idleCutoff)
                ->latest('last_activity_at')
                ->first();

            if ($current) {
                $current->update(['last_activity_at' => $now]);
                return $current;
            }

            // Close any stale open visits (idle past the window) so
            // their durations stop accruing at the last real activity.
            UserVisitSession::query()
                ->where('user_id', $userId)
                ->whereNull('ended_at')
                ->where('last_activity_at', '<', $idleCutoff)
                ->update(['ended_at' => \DB::raw('last_activity_at')]);

            // Start a fresh visit.
            return UserVisitSession::create([
                'user_id'          => $userId,
                'started_at'       => $now,
                'last_activity_at' => $now,
                'ip'               => $request->ip(),
                'user_agent'       => substr((string) $request->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            Log::warning('UserActivityLogger::touchVisitSession failed', ['err' => $e->getMessage()]);
            return null;
        }
    }

    /** Close the current visit on explicit logout. */
    public function closeVisitSession(int $userId): void
    {
        try {
            UserVisitSession::query()
                ->where('user_id', $userId)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('UserActivityLogger::closeVisitSession failed', ['err' => $e->getMessage()]);
        }
    }

    /** Generic write — internal. */
    private function write(
        Request $request,
        int $userId,
        string $type,
        ?array $details = null,
        ?int $relatedClinicId = null,
        ?string $relatedUrl = null,
    ): void {
        try {
            // Resolve current visit lazily so callers don't have to
            // thread the id through. Most activity log writes happen
            // right after the middleware has already touched the visit.
            $visit = UserVisitSession::query()
                ->where('user_id', $userId)
                ->whereNull('ended_at')
                ->latest('last_activity_at')
                ->first();

            UserActivityEvent::create([
                'user_id'           => $userId,
                'type'              => $type,
                'title'             => null,
                'details'           => $details,
                'related_clinic_id' => $relatedClinicId,
                'related_url'       => $relatedUrl,
                'visit_session_id'  => $visit?->id,
                'ip'                => $request->ip(),
                'user_agent'        => substr((string) $request->userAgent(), 0, 255),
                'occurred_at'       => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('UserActivityLogger::write failed', [
                'err'     => $e->getMessage(),
                'type'    => $type,
                'user_id' => $userId,
            ]);
        }
    }
}
