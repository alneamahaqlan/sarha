<?php

namespace App\Http\Controllers\Api\V1\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ClinicActivityLogResource;
use App\Models\ClinicActivityLog;
use App\Models\ClinicTeamMember;
use App\Support\ActingClinicUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Owner-only feed of the clinic's internal activity. Supports filters
 * by actor (member/owner), event type, and date range; paginated so
 * a long-lived clinic doesn't blow up the bell or the React table.
 */
class TeamActivityController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $clinicId = ActingClinicUser::clinicId();

        $q = ClinicActivityLog::query()->forClinic($clinicId);

        // Filter by specific team member (or "owner" sentinel).
        if ($request->filled('actor_id')) {
            $actorId = (int) $request->input('actor_id');
            // We always store the actor as the polymorphic type/id pair
            // — owner rows have actor_type=Clinic, member rows have
            // actor_type=ClinicTeamMember. The filter accepts the
            // numeric id; the type is inferred from the table.
            if ($request->input('actor_type') === 'member') {
                $q->where('actor_type', ClinicTeamMember::class)->where('actor_id', $actorId);
            } else {
                $q->where('actor_type', \App\Models\Clinic::class)->where('actor_id', $actorId);
            }
        }

        // Filter by event family (e.g. "booking.*" or exact "team.member_added").
        if ($request->filled('action')) {
            $action = (string) $request->input('action');
            $q->where(function ($qq) use ($action) {
                if (str_contains($action, '*')) {
                    $qq->where('action', 'like', str_replace('*', '%', $action));
                } else {
                    $qq->where('action', $action);
                }
            });
        }

        // Quick date windows: today / 7d / 30d, OR explicit from/to.
        if ($request->filled('period')) {
            $days = match ($request->input('period')) {
                'today' => 0,
                'week'  => 7,
                'month' => 30,
                default => null,
            };
            if ($days !== null) {
                $q->where('created_at', '>=', now()->subDays($days)->startOfDay());
            }
        }
        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->input('to'));
        }

        // Free-text search across actor_name + summary JSON.
        if ($request->filled('q')) {
            $like = '%' . $request->input('q') . '%';
            $q->where(function ($qq) use ($like) {
                $qq->where('actor_name', 'like', $like)
                   ->orWhere('action', 'like', $like)
                   ->orWhereRaw('JSON_SEARCH(summary, "one", ?) IS NOT NULL', [$like]);
            });
        }

        $logs = $q->orderByDesc('created_at')->paginate(30)->withQueryString();

        return ClinicActivityLogResource::collection($logs);
    }
}
