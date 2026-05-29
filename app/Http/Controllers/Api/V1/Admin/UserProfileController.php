<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiAssistantLog;
use App\Models\AiUserInteractionSummary;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Complaint;
use App\Models\CustomerReport;
use App\Models\PriceQuoteRequest;
use App\Models\User;
use App\Models\UserActivityEvent;
use App\Models\UserVisitSession;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Backs the super-admin "comprehensive user profile" screen. Each
 * endpoint serves a single React panel so the page can hydrate the
 * header instantly and lazy-load timeline / tabs / hours chart in
 * parallel.
 *
 *  - show()      header + summary cards + behavioral insights + risk flags
 *  - timeline()  unified, paginated activity stream (union of events
 *                from many domain tables, sorted by occurred_at)
 *  - tab($tab)   detail tables (bookings, complaints, ai, sessions,
 *                favorites, quotes, account_edits) — lazy-loaded per tab
 *  - hours()     activity-by-hour-of-day × day-of-week heatmap data
 *
 * Privacy: phone/email are returned masked unless the caller passes
 * ?reveal=1 (the React "show" button). Every show() call is recorded
 * in the audit log so admin access stays accountable.
 */
class UserProfileController extends Controller
{
    /** Engagement-level cutoffs (days since last activity event). */
    private const ENGAGEMENT_ACTIVE_DAYS = 7;
    private const ENGAGEMENT_MEDIUM_DAYS = 30;

    public function show(Request $request, User $user): JsonResponse
    {
        // Audit every profile view — spec line:
        // "كل فتح لملف مستخدم يُسجَّل في سجل المراجعة".
        AuditLogService::log('user.profile_viewed', $user);

        $reveal = $request->boolean('reveal');

        return response()->json([
            'data' => [
                'user'           => $this->headerPayload($user, $reveal),
                'badges'         => $this->headerBadges($user),
                'summary_cards'  => $this->summaryCards($user),
                'insights'       => $this->behavioralInsights($user),
                'risk_flags'     => $this->riskFlags($user),
            ],
        ]);
    }

    /**
     * Cursor-style timeline pagination by occurred_at. Filters: types[],
     * date range (from/to), clinic_id, q (text). We union over the
     * domain tables instead of duplicating their rows into a parallel
     * "events" table.
     */
    public function timeline(Request $request, User $user): JsonResponse
    {
        $types = (array) $request->input('types', []);
        $from  = $request->date('from');
        $to    = $request->date('to');
        $clinicId = $request->filled('clinic_id') ? (int) $request->input('clinic_id') : null;
        $q = (string) $request->input('q', '');

        $events = $this->collectTimelineEvents($user, $types, $from, $to, $clinicId, $q);

        // Sort + paginate in memory — the union pull is bounded per
        // source (last 500 rows each) so the worst-case row count is
        // ~3500. A single user's timeline never approaches that on
        // real data. If it ever does, we'd shift to a SQL union.
        $events = $events->sortByDesc('occurred_at')->values();
        $perPage = min(max((int) $request->input('per_page', 30), 10), 100);
        $page = max((int) $request->input('page', 1), 1);
        $total = $events->count();
        $sliced = $events->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => $sliced,
            'meta' => [
                'total'      => $total,
                'per_page'   => $perPage,
                'page'       => $page,
                'last_page'  => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    /** Lazy-loaded detail tab. Each tab returns its own paginated list. */
    public function tab(Request $request, User $user, string $tab): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(max((int) $request->input('per_page', 15), 5), 50);

        return response()->json([
            'data' => match ($tab) {
                'bookings'      => $this->tabBookings($user, $page, $perPage),
                'complaints'    => $this->tabComplaints($user, $page, $perPage),
                'ai'            => $this->tabAi($user, $page, $perPage),
                'sessions'      => $this->tabSessions($user, $page, $perPage),
                'favorites'     => $this->tabFavorites($user, $page, $perPage),
                'quotes'        => $this->tabQuotes($user, $page, $perPage),
                'account_edits' => $this->tabAccountEdits($user, $page, $perPage),
                default         => abort(404, "Unknown tab '{$tab}'"),
            },
        ]);
    }

    /** Hour-of-day × day-of-week counts for the "activity hours" heatmap. */
    public function hours(User $user): JsonResponse
    {
        // Pull occurred_at from all activity sources, bin by (dow, hour).
        $buckets = array_fill(0, 7, array_fill(0, 24, 0));

        $sources = [
            UserActivityEvent::where('user_id', $user->id)->pluck('occurred_at'),
            Booking::where('user_id', $user->id)->pluck('created_at'),
            Complaint::where('user_id', $user->id)->pluck('created_at'),
            AiAssistantLog::where('user_id', $user->id)->pluck('created_at'),
            PriceQuoteRequest::where('user_id', $user->id)->pluck('created_at'),
        ];

        foreach ($sources as $col) {
            foreach ($col as $at) {
                if (! $at instanceof Carbon) {
                    $at = Carbon::parse((string) $at);
                }
                $buckets[$at->dayOfWeek][$at->hour]++;
            }
        }

        return response()->json(['data' => ['matrix' => $buckets]]);
    }

    // ────────────────────────────── Header ──────────────────────────────

    private function headerPayload(User $user, bool $reveal): array
    {
        return [
            'id'           => $user->id,
            'name'         => $user->name,
            'initials'     => $this->initials($user->name),
            'phone'        => $reveal ? $user->phone : $this->maskPhone($user->phone),
            'phone_masked' => ! $reveal,
            'email'        => $reveal ? $user->email : $this->maskEmail($user->email),
            'email_masked' => ! $reveal,
            'is_active'    => (bool) $user->is_active,
            'created_at'   => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * Quick-glance pills at the top of the profile. Counts are kept
     * lightweight (single SELECT each) so the header renders in one
     * round-trip even for users with thousands of rows.
     */
    private function headerBadges(User $user): array
    {
        return [
            'member_since_days' => $user->created_at?->diffInDays(now()) ?? 0,
            'bookings_count'    => $user->bookings()->count(),
            'complaints_count'  => Complaint::where('user_id', $user->id)->count(),
            'engagement_level'  => $this->computeEngagementLevel($user),
        ];
    }

    // ─────────────────────────── Summary cards ───────────────────────────

    private function summaryCards(User $user): array
    {
        $visits = UserVisitSession::where('user_id', $user->id)->get();

        $totalSeconds = 0;
        foreach ($visits as $v) {
            $end = $v->effectiveEnd();
            if ($v->started_at && $end) {
                // abs() — Carbon 3 returns signed diffs by default.
                $totalSeconds += (int) abs($end->diffInSeconds($v->started_at));
            }
        }

        $sessionCount = $visits->count();
        $avgSeconds   = $sessionCount > 0 ? (int) round($totalSeconds / $sessionCount) : 0;
        $lastVisit    = $visits->sortByDesc('last_activity_at')->first();

        $topCategory = $this->topCategoryName($user);
        $topCity     = $this->topCityName($user);

        return [
            'sessions_total'        => $sessionCount,
            'time_on_platform_seconds' => $totalSeconds,
            'last_login_at'         => $lastVisit?->started_at?->toIso8601String(),
            'last_login_user_agent' => $lastVisit?->user_agent,
            'last_login_ip'         => $lastVisit?->ip,
            'avg_session_seconds'   => $avgSeconds,
            'top_category'          => $topCategory,
            'top_city'              => $topCity,
        ];
    }

    // ─────────────────────────── Behavioral ───────────────────────────

    private function behavioralInsights(User $user): array
    {
        $latestSummary = AiUserInteractionSummary::where('user_id', $user->id)
            ->latest('generated_at')->first();

        return [
            'engagement_level' => $this->computeEngagementLevel($user),
            'decision_stage'   => $latestSummary?->seriousness, // exploration | comparing | near_decision
            'preferred_city'   => $this->topCityName($user),
            'preferred_category' => $this->topCategoryName($user),
        ];
    }

    /**
     * Spec lists 4 categories of risk: complaints>3/month, repeated
     * cancellations, suspicious activity (geo-spread), search-no-book.
     * Each flag carries a count so the UI can show "3 complaints in
     * 28 days" rather than a generic boolean.
     */
    private function riskFlags(User $user): array
    {
        $flags = [];
        $now = now();

        $complaintsLastMonth = Complaint::where('user_id', $user->id)
            ->where('created_at', '>=', $now->copy()->subDays(30))->count();
        if ($complaintsLastMonth > 3) {
            $flags[] = ['code' => 'repeated_complaints', 'count' => $complaintsLastMonth];
        }

        $cancelledRecently = Booking::where('user_id', $user->id)
            ->where('status', 'cancelled')
            ->where('created_at', '>=', $now->copy()->subDays(30))->count();
        if ($cancelledRecently >= 3) {
            $flags[] = ['code' => 'repeated_cancellations', 'count' => $cancelledRecently];
        }

        // Geo-spread: visit sessions in last 24h coming from distinct
        // IPs (cheap proxy for "logged in from very different places").
        $recentIps = UserVisitSession::where('user_id', $user->id)
            ->where('started_at', '>=', $now->copy()->subHours(24))
            ->whereNotNull('ip')->distinct()->pluck('ip');
        if ($recentIps->count() >= 4) {
            $flags[] = ['code' => 'suspicious_activity', 'count' => $recentIps->count()];
        }

        // Browsed a lot, never booked — possible bot or window-shopper.
        $searches = UserActivityEvent::where('user_id', $user->id)
            ->whereIn('type', [UserActivityEvent::TYPE_SEARCH, UserActivityEvent::TYPE_FILTER])->count();
        $bookings = $user->bookings()->count();
        if ($searches >= 20 && $bookings === 0) {
            $flags[] = ['code' => 'search_no_booking', 'count' => $searches];
        }

        return $flags;
    }

    // ─────────────────────────── Timeline pieces ───────────────────────────

    private function collectTimelineEvents(User $user, array $types, ?Carbon $from, ?Carbon $to, ?int $clinicId, string $q): \Illuminate\Support\Collection
    {
        // Helper: range filter applied once at the source level.
        $rangeWhere = function ($query, string $col) use ($from, $to) {
            if ($from) $query->where($col, '>=', $from->startOfDay());
            if ($to)   $query->where($col, '<=', $to->endOfDay());
            return $query;
        };

        $textMatches = fn (?string $text): bool => $q === '' || ($text !== null && mb_stripos($text, $q) !== false);
        $wantsType = fn (string $type): bool => empty($types) || in_array($type, $types, true);

        $events = collect();

        // 1. UserActivityEvent rows (login/search/filter/clinic_view/etc.).
        if (empty($types) || array_intersect($types, UserActivityEvent::ALL_TYPES)) {
            $eventQuery = UserActivityEvent::where('user_id', $user->id);
            if (! empty($types)) {
                $eventQuery->whereIn('type', array_intersect($types, UserActivityEvent::ALL_TYPES));
            }
            if ($clinicId) {
                $eventQuery->where('related_clinic_id', $clinicId);
            }
            $rangeWhere($eventQuery, 'occurred_at');
            foreach ($eventQuery->latest('occurred_at')->limit(500)->get() as $row) {
                $details = $row->details ?? [];
                $title = $this->renderActivityTitle($row);
                if (! $textMatches($title)) continue;
                $events->push([
                    'id'          => 'evt_' . $row->id,
                    'type'        => $row->type,
                    'title'       => $title,
                    'details'     => $details,
                    'related_clinic_id' => $row->related_clinic_id,
                    'related_url' => $row->related_url,
                    'occurred_at' => $row->occurred_at?->toIso8601String(),
                ]);
            }
        }

        // 2. Bookings — type "booking_created" + "booking_cancelled".
        if ($wantsType('booking_created') || empty($types)) {
            $q1 = Booking::where('user_id', $user->id)->with('clinic:id,name,slug', 'service:id,name');
            if ($clinicId) $q1->where('clinic_id', $clinicId);
            $rangeWhere($q1, 'created_at');
            foreach ($q1->latest('created_at')->limit(500)->get() as $b) {
                $title = trans('admin.user_profile.timeline.booking_created', [
                    'clinic'  => $b->clinic?->name ?? '—',
                    'service' => $b->service?->name ?? '—',
                ]);
                if (! $textMatches($title)) continue;
                $events->push([
                    'id'          => 'bk_' . $b->id,
                    'type'        => 'booking_created',
                    'title'       => $title,
                    'details'     => ['status' => $b->status, 'reference' => $b->reference_code],
                    'related_clinic_id' => $b->clinic_id,
                    'related_url' => "/app/admin/bookings?search={$b->reference_code}",
                    'occurred_at' => $b->created_at?->toIso8601String(),
                ]);
                if ($b->status === 'cancelled') {
                    $events->push([
                        'id'          => 'bkc_' . $b->id,
                        'type'        => 'booking_cancelled',
                        'title'       => trans('admin.user_profile.timeline.booking_cancelled', [
                            'clinic' => $b->clinic?->name ?? '—',
                        ]),
                        'details'     => ['reference' => $b->reference_code],
                        'related_clinic_id' => $b->clinic_id,
                        'related_url' => "/app/admin/bookings?search={$b->reference_code}",
                        'occurred_at' => $b->updated_at?->toIso8601String() ?? $b->created_at?->toIso8601String(),
                    ]);
                }
            }
        }

        // 3. Complaints.
        if ($wantsType('complaint_created') || empty($types)) {
            $q2 = Complaint::where('user_id', $user->id)->with('clinic:id,name');
            if ($clinicId) $q2->where('clinic_id', $clinicId);
            $rangeWhere($q2, 'created_at');
            foreach ($q2->latest('created_at')->limit(200)->get() as $c) {
                $title = trans('admin.user_profile.timeline.complaint_created', [
                    'subject' => $c->subject ?? '',
                ]);
                if (! $textMatches($title)) continue;
                $events->push([
                    'id'          => 'cmp_' . $c->id,
                    'type'        => 'complaint_created',
                    'title'       => $title,
                    'details'     => ['status' => $c->status, 'priority' => $c->priority],
                    'related_clinic_id' => $c->clinic_id,
                    'related_url' => "/app/admin/complaints?search={$c->reference_code}",
                    'occurred_at' => $c->created_at?->toIso8601String(),
                ]);
            }
        }

        // 4. AI conversations (deduplicate by conversation_id so a 10-turn
        // chat is one event, not ten).
        if ($wantsType('ai_conversation') || empty($types)) {
            $q3 = AiAssistantLog::where('user_id', $user->id);
            $rangeWhere($q3, 'created_at');
            $logs = $q3->latest('created_at')->limit(500)->get();
            $byConvo = $logs->groupBy(fn ($r) => $r->conversation_id ?? 'log_' . $r->id);
            foreach ($byConvo as $convoId => $rows) {
                $first = $rows->sortBy('created_at')->first();
                $last  = $rows->sortByDesc('created_at')->first();
                $title = trans('admin.user_profile.timeline.ai_conversation', [
                    'turns' => $rows->count(),
                    'query' => mb_strimwidth((string) $first->query, 0, 60, '…'),
                ]);
                if (! $textMatches($title)) continue;
                $events->push([
                    'id'          => 'ai_' . $convoId,
                    'type'        => 'ai_conversation',
                    'title'       => $title,
                    'details'     => [
                        'turns'           => $rows->count(),
                        'conversation_id' => $first->conversation_id,
                        'was_emergency'   => (bool) $rows->contains('was_emergency', true),
                        'was_blocked'     => (bool) $rows->contains('was_blocked', true),
                    ],
                    'related_clinic_id' => null,
                    'related_url' => $first->conversation_id
                        ? '/app/admin/ai-center?conversation=' . $first->conversation_id
                        : '/app/admin/ai-center',
                    'occurred_at' => $last->created_at?->toIso8601String(),
                ]);
            }
        }

        // 5. Favorites — track add events from the pivot timestamps.
        if ($wantsType('favorite_added') || empty($types)) {
            $favs = DB::table('favorites')
                ->where('user_id', $user->id)
                ->join('clinics', 'favorites.clinic_id', '=', 'clinics.id')
                ->select('favorites.clinic_id', 'clinics.name as clinic_name', 'clinics.slug as slug', 'favorites.created_at')
                ->when($clinicId, fn ($qq) => $qq->where('favorites.clinic_id', $clinicId))
                ->when($from, fn ($qq) => $qq->where('favorites.created_at', '>=', $from->startOfDay()))
                ->when($to,   fn ($qq) => $qq->where('favorites.created_at', '<=', $to->endOfDay()))
                ->orderByDesc('favorites.created_at')->limit(200)->get();
            foreach ($favs as $f) {
                $title = trans('admin.user_profile.timeline.favorite_added', ['clinic' => $f->clinic_name]);
                if (! $textMatches($title)) continue;
                $events->push([
                    'id'    => 'fav_' . $f->clinic_id . '_' . strtotime((string) $f->created_at),
                    'type'  => 'favorite_added',
                    'title' => $title,
                    'details' => [],
                    'related_clinic_id' => $f->clinic_id,
                    'related_url' => "/clinic/{$f->slug}",
                    'occurred_at' => $f->created_at,
                ]);
            }
        }

        // 6. Quote requests.
        if ($wantsType('quote_request') || empty($types)) {
            $q4 = PriceQuoteRequest::where('user_id', $user->id);
            if ($clinicId) $q4->where('clinic_id', $clinicId);
            $rangeWhere($q4, 'created_at');
            foreach ($q4->latest('created_at')->limit(200)->get() as $qr) {
                $title = trans('admin.user_profile.timeline.quote_request', [
                    'service' => $qr->service_name ?? '—',
                ]);
                if (! $textMatches($title)) continue;
                $events->push([
                    'id'    => 'q_' . $qr->id,
                    'type'  => 'quote_request',
                    'title' => $title,
                    'details' => ['status' => $qr->status],
                    'related_clinic_id' => $qr->clinic_id,
                    'related_url' => "/app/admin/quotes?search={$qr->id}",
                    'occurred_at' => $qr->created_at?->toIso8601String(),
                ]);
            }
        }

        return $events;
    }

    private function renderActivityTitle(UserActivityEvent $row): string
    {
        $d = $row->details ?? [];
        $clinic = $row->related_clinic_id
            ? Clinic::where('id', $row->related_clinic_id)->value('name')
            : null;

        return match ($row->type) {
            UserActivityEvent::TYPE_LOGIN        => trans('admin.user_profile.timeline.login'),
            UserActivityEvent::TYPE_LOGOUT       => trans('admin.user_profile.timeline.logout'),
            UserActivityEvent::TYPE_OTP_VERIFIED => trans('admin.user_profile.timeline.otp_verified'),
            UserActivityEvent::TYPE_SEARCH       => trans('admin.user_profile.timeline.search', ['q' => $d['q'] ?? '']),
            UserActivityEvent::TYPE_FILTER       => trans('admin.user_profile.timeline.filter'),
            UserActivityEvent::TYPE_CLINIC_VIEW  => trans('admin.user_profile.timeline.clinic_view', ['clinic' => $clinic ?? '—']),
            UserActivityEvent::TYPE_COMPARE      => trans('admin.user_profile.timeline.compare', ['count' => count($d['clinic_ids'] ?? [])]),
            UserActivityEvent::TYPE_ACTION_CLICK => trans('admin.user_profile.timeline.action_click', [
                'button' => $d['button'] ?? '',
                'clinic' => $clinic ?? '—',
            ]),
            UserActivityEvent::TYPE_ACCOUNT_EDIT => trans('admin.user_profile.timeline.account_edit'),
            default => $row->type,
        };
    }

    // ─────────────────────────── Tab loaders ───────────────────────────

    private function tabBookings(User $user, int $page, int $perPage): array
    {
        $paginator = $user->bookings()
            ->with(['clinic:id,name', 'service:id,name', 'relative'])
            ->latest()->paginate($perPage, ['*'], 'page', $page);

        return [
            'rows' => collect($paginator->items())->map(fn (Booking $b) => [
                'id'             => $b->id,
                'reference_code' => $b->reference_code,
                'clinic'         => $b->clinic ? ['id' => $b->clinic->id, 'name' => $b->clinic->name] : null,
                'service'        => $b->service ? ['name' => $b->service->name] : null,
                'status'         => $b->status,
                'for_relative'   => $b->relative_id !== null,
                'patient_name'   => $b->customer_name,
                'created_at'     => $b->created_at?->toIso8601String(),
            ])->all(),
            'meta' => $this->paginatorMeta($paginator),
        ];
    }

    private function tabComplaints(User $user, int $page, int $perPage): array
    {
        $paginator = Complaint::where('user_id', $user->id)
            ->with('clinic:id,name')
            ->latest()->paginate($perPage, ['*'], 'page', $page);

        return [
            'rows' => collect($paginator->items())->map(fn (Complaint $c) => [
                'id'         => $c->id,
                'reference_code' => $c->reference_code,
                'subject'    => $c->subject,
                'status'     => $c->status,
                'priority'   => $c->priority,
                'type'       => $c->type,
                'clinic'     => $c->clinic ? ['id' => $c->clinic->id, 'name' => $c->clinic->name] : null,
                'created_at' => $c->created_at?->toIso8601String(),
            ])->all(),
            'meta' => $this->paginatorMeta($paginator),
        ];
    }

    private function tabAi(User $user, int $page, int $perPage): array
    {
        // Conversations table from the AI logs (grouped on conversation_id).
        $logs = AiAssistantLog::where('user_id', $user->id)->latest()->limit(500)->get();
        $convos = $logs->groupBy(fn ($r) => $r->conversation_id ?? 'log_' . $r->id)
            ->map(function ($rows, $convoId) {
                $first = $rows->sortBy('created_at')->first();
                $last  = $rows->sortByDesc('created_at')->first();
                return [
                    'conversation_id' => $first->conversation_id,
                    'turns'           => $rows->count(),
                    'first_query'     => $first->query,
                    'last_at'         => $last->created_at?->toIso8601String(),
                    'was_emergency'   => (bool) $rows->contains('was_emergency', true),
                    'was_blocked'     => (bool) $rows->contains('was_blocked', true),
                ];
            })->values();

        $total = $convos->count();
        $rows = $convos->slice(($page - 1) * $perPage, $perPage)->values();

        $summary = AiUserInteractionSummary::where('user_id', $user->id)
            ->latest('generated_at')->first();

        return [
            'rows' => $rows->all(),
            'meta' => [
                'total' => $total, 'per_page' => $perPage, 'page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
            'latest_summary' => $summary ? [
                'topic'       => $summary->topic,
                'seriousness' => $summary->seriousness,
                'generated_at' => $summary->generated_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function tabSessions(User $user, int $page, int $perPage): array
    {
        $paginator = UserVisitSession::where('user_id', $user->id)
            ->latest('started_at')->paginate($perPage, ['*'], 'page', $page);

        return [
            'rows' => collect($paginator->items())->map(fn (UserVisitSession $v) => [
                'id'               => $v->id,
                'started_at'       => $v->started_at?->toIso8601String(),
                'last_activity_at' => $v->last_activity_at?->toIso8601String(),
                'ended_at'         => $v->ended_at?->toIso8601String(),
                'duration_seconds' => $v->started_at && $v->effectiveEnd()
                    ? (int) abs($v->effectiveEnd()->diffInSeconds($v->started_at))
                    : 0,
                'ip'               => $v->ip,
                'user_agent'       => $v->user_agent,
            ])->all(),
            'meta' => $this->paginatorMeta($paginator),
        ];
    }

    private function tabFavorites(User $user, int $page, int $perPage): array
    {
        $paginator = DB::table('favorites')
            ->where('user_id', $user->id)
            ->join('clinics', 'favorites.clinic_id', '=', 'clinics.id')
            ->select('favorites.clinic_id', 'clinics.name as clinic_name', 'clinics.slug as slug', 'favorites.created_at')
            ->orderByDesc('favorites.created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'rows' => collect($paginator->items())->map(fn ($f) => [
                'clinic_id' => $f->clinic_id,
                'name'      => $f->clinic_name,
                'slug'      => $f->slug,
                'created_at'=> $f->created_at,
            ])->all(),
            'meta' => $this->paginatorMeta($paginator),
        ];
    }

    private function tabQuotes(User $user, int $page, int $perPage): array
    {
        $paginator = PriceQuoteRequest::where('user_id', $user->id)
            ->latest()->paginate($perPage, ['*'], 'page', $page);

        return [
            'rows' => collect($paginator->items())->map(fn (PriceQuoteRequest $q) => [
                'id'           => $q->id,
                'service_name' => $q->service_name,
                'status'       => $q->status,
                'clinic_id'    => $q->clinic_id,
                'created_at'   => $q->created_at?->toIso8601String(),
            ])->all(),
            'meta' => $this->paginatorMeta($paginator),
        ];
    }

    private function tabAccountEdits(User $user, int $page, int $perPage): array
    {
        $paginator = UserActivityEvent::where('user_id', $user->id)
            ->where('type', UserActivityEvent::TYPE_ACCOUNT_EDIT)
            ->latest('occurred_at')->paginate($perPage, ['*'], 'page', $page);

        return [
            'rows' => collect($paginator->items())->map(fn (UserActivityEvent $e) => [
                'id'          => $e->id,
                'changes'     => $e->details['changes'] ?? [],
                'occurred_at' => $e->occurred_at?->toIso8601String(),
                'ip'          => $e->ip,
            ])->all(),
            'meta' => $this->paginatorMeta($paginator),
        ];
    }

    // ─────────────────────────── Small helpers ───────────────────────────

    private function paginatorMeta($paginator): array
    {
        return [
            'total'     => $paginator->total(),
            'per_page'  => $paginator->perPage(),
            'page'      => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    private function computeEngagementLevel(User $user): string
    {
        $lastEvent = UserActivityEvent::where('user_id', $user->id)
            ->latest('occurred_at')->value('occurred_at');
        $lastVisit = UserVisitSession::where('user_id', $user->id)
            ->latest('last_activity_at')->value('last_activity_at');
        $candidates = collect([$lastEvent, $lastVisit])->filter()->map(fn ($v) => Carbon::parse($v));
        if ($candidates->isEmpty()) return 'idle';
        $latest = $candidates->max();
        $days = $latest->diffInDays(now());
        if ($days <= self::ENGAGEMENT_ACTIVE_DAYS) return 'active';
        if ($days <= self::ENGAGEMENT_MEDIUM_DAYS) return 'medium';
        return 'idle';
    }

    private function topCategoryName(User $user): ?string
    {
        // Prefer searches (intent) over favorites (history).
        $catId = UserActivityEvent::where('user_id', $user->id)
            ->whereIn('type', [UserActivityEvent::TYPE_SEARCH, UserActivityEvent::TYPE_FILTER])
            ->whereNotNull('details->category_id')
            ->selectRaw("JSON_EXTRACT(details, '$.category_id') as cat, COUNT(*) as c")
            ->groupBy('cat')->orderByDesc('c')->limit(1)->value('cat');
        if (! $catId) return null;
        $catId = (int) trim((string) $catId, '"');
        if (! $catId) return null;
        $cat = \App\Models\Category::find($catId);
        return $cat?->display_name;
    }

    private function topCityName(User $user): ?string
    {
        $cityId = UserActivityEvent::where('user_id', $user->id)
            ->whereIn('type', [UserActivityEvent::TYPE_SEARCH, UserActivityEvent::TYPE_FILTER])
            ->whereNotNull('details->city_id')
            ->selectRaw("JSON_EXTRACT(details, '$.city_id') as cid, COUNT(*) as c")
            ->groupBy('cid')->orderByDesc('c')->limit(1)->value('cid');
        if (! $cityId) return null;
        $cityId = (int) trim((string) $cityId, '"');
        if (! $cityId) return null;
        return \App\Models\City::where('id', $cityId)->value('name');
    }

    private function initials(?string $name): string
    {
        if (! $name) return '?';
        $parts = preg_split('/\s+/', trim($name));
        if (! $parts) return '?';
        $first = mb_substr($parts[0], 0, 1);
        $second = isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '';
        return mb_strtoupper($first . $second);
    }

    private function maskPhone(?string $phone): ?string
    {
        if (! $phone) return null;
        // Keep first 3 + last 2 visible — enough for the admin to
        // recognize a known number without exposing the full digits.
        $len = mb_strlen($phone);
        if ($len <= 5) return str_repeat('•', $len);
        return mb_substr($phone, 0, 3) . str_repeat('•', $len - 5) . mb_substr($phone, $len - 2);
    }

    private function maskEmail(?string $email): ?string
    {
        if (! $email) return null;
        if (! str_contains($email, '@')) return $email;
        [$local, $domain] = explode('@', $email, 2);
        $first = mb_substr($local, 0, 1);
        return $first . str_repeat('•', max(0, mb_strlen($local) - 1)) . '@' . $domain;
    }
}
