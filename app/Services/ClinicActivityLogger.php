<?php

namespace App\Services;

use App\Models\ClinicActivityLog;
use App\Support\ActingClinicUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Records clinic-side actions into `clinic_activity_logs`. Pulls the
 * actor (Clinic owner or ClinicTeamMember) from ActingClinicUser so
 * call sites never have to construct it manually.
 *
 * The logger swallows its own exceptions — activity is observability,
 * never a hard dependency. A failed write should not break the user's
 * action (e.g. booking confirmation must succeed even if the log row
 * fails to insert).
 *
 * Convention: action codes are dotted slugs like "booking.confirmed",
 * "service.created", "team.member_added". The React layer translates
 * them via i18n.
 */
class ClinicActivityLogger
{
    public function log(string $action, ?Model $subject = null, array $summary = []): ?ClinicActivityLog
    {
        $clinicId = ActingClinicUser::clinicId();
        if (! $clinicId) {
            // Background runs (seeders, console commands without auth)
            // are silently ignored — they're explicitly out of scope
            // per spec ("سجل نشاط الفريق").
            return null;
        }

        try {
            return ClinicActivityLog::create([
                'clinic_id'  => $clinicId,
                'actor_type' => ActingClinicUser::actorType(),
                'actor_id'   => ActingClinicUser::actorId(),
                'actor_name' => ActingClinicUser::actorName() ?? '—',
                'actor_role' => ActingClinicUser::role()->value,
                'action'     => $action,
                'model_type' => $subject ? $subject::class : null,
                'model_id'   => $subject?->getKey(),
                'summary'    => $summary,
                'ip_address' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ClinicActivityLogger::log failed', [
                'action' => $action,
                'err'    => $e->getMessage(),
            ]);
            return null;
        }
    }
}
