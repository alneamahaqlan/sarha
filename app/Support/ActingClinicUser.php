<?php

namespace App\Support;

use App\Enums\ClinicRole;
use App\Models\Clinic;
use App\Models\ClinicTeamMember;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Single source of truth for "who is acting on behalf of which clinic
 * right now?". Every clinic-side controller, observer, and middleware
 * routes through here instead of touching `auth('clinic')` directly so
 * the team-member overlay stays transparent to existing code.
 *
 * Rules:
 *   - `auth('clinic')->user()` always returns the owning Clinic (we
 *     log in the Clinic itself, then optionally stamp the session
 *     with the acting team member's id).
 *   - `actor()` returns whichever is true: the ClinicTeamMember (if
 *     session is stamped) or the Clinic (owner login).
 *   - `clinicId()` always returns the owning clinic's id (works for
 *     both owner and member sessions).
 *   - `role()` derives the role from the actor type.
 *
 * Session key: `clinic_team_member_id`. Set by AuthController on
 * member login. Cleared on logout.
 */
class ActingClinicUser
{
    public const SESSION_MEMBER_KEY = 'clinic_team_member_id';

    /**
     * The clinic whose data we're operating on. Returns null when no
     * clinic session is active.
     */
    public static function clinicId(): ?int
    {
        $clinic = Auth::guard('clinic')->user();
        return $clinic?->getKey();
    }

    /**
     * The acting entity — either the team member (if member session)
     * or the clinic itself (owner session). Returns null when not
     * authenticated.
     */
    public static function actor(): Clinic|ClinicTeamMember|null
    {
        $clinic = Auth::guard('clinic')->user();
        if (! $clinic instanceof Clinic) return null;

        $memberId = Session::get(self::SESSION_MEMBER_KEY);
        if (! $memberId) return $clinic;

        // Defensive: a stamped session whose member row has been
        // deleted falls back to the owner identity so the user stays
        // operational until they re-login.
        $member = ClinicTeamMember::where('id', $memberId)
            ->where('clinic_id', $clinic->id)
            ->first();

        return $member ?? $clinic;
    }

    /**
     * Role of the acting entity. Owner sessions always return OWNER;
     * member sessions return the member's stored role.
     */
    public static function role(): ClinicRole
    {
        $actor = self::actor();
        if ($actor instanceof ClinicTeamMember) {
            return ClinicRole::from($actor->role);
        }
        return ClinicRole::OWNER;
    }

    /**
     * Display name of the acting entity — used by the activity logger
     * as a snapshot so removing the member later doesn't blank out
     * the row in the activity log.
     */
    public static function actorName(): ?string
    {
        $actor = self::actor();
        if ($actor instanceof ClinicTeamMember) return $actor->name;
        if ($actor instanceof Clinic) return $actor->name;
        return null;
    }

    /**
     * Convenience for activity log polymorph payloads.
     */
    public static function actorType(): ?string
    {
        $actor = self::actor();
        return $actor ? $actor::class : null;
    }

    public static function actorId(): ?int
    {
        return self::actor()?->getKey();
    }

    /**
     * Stamp the session with the acting member id. Called from
     * AuthController::login() right after the parent Clinic guard
     * is established.
     */
    public static function setActingMember(ClinicTeamMember $member): void
    {
        Session::put(self::SESSION_MEMBER_KEY, $member->id);
    }

    /**
     * Clear the member stamp — called on logout so the next login
     * (which may be an owner) starts clean.
     */
    public static function clearActingMember(): void
    {
        Session::forget(self::SESSION_MEMBER_KEY);
    }

    /**
     * Convenience permission check used by controllers + middleware.
     */
    public static function can(string $ability): bool
    {
        return self::role()->can($ability);
    }
}
