/**
 * Types for the clinic team-members feature. Mirrors the backend's
 * ClinicTeamMemberResource + ClinicActivityLogResource shapes.
 */

export type ClinicRole = 'owner' | 'coordinator' | 'reception';

export type RoleColorToken = 'gold' | 'info' | 'muted';

export interface ClinicTeamMember {
  id: number;
  clinic_id: number;
  name: string;
  phone: string;
  role: ClinicRole;
  role_color: RoleColorToken;
  is_active: boolean;
  last_login_at: string | null;
  created_at: string | null;
  /** Set when the member was soft-removed; UI shows "غير نشط" badge. */
  deleted_at: string | null;
}

export interface TeamMemberFormValues {
  name: string;
  phone: string;
  role: Exclude<ClinicRole, 'owner'>;
}

export interface TeamMemberEditValues {
  name?: string;
  role?: Exclude<ClinicRole, 'owner'>;
  is_active?: boolean;
}

/**
 * Response from `store` / `regeneratePassword` — backend returns the
 * generated temp password ONCE so the owner can copy + hand it over.
 */
export interface TeamMemberWithTempPassword {
  data: ClinicTeamMember;
  temp_password: string;
}

// ───────── Activity log ─────────

export interface ClinicActivityLog {
  id: number;
  actor_id: number | null;
  actor_type: string | null;
  actor_name: string;
  actor_role: ClinicRole | null;
  actor_color: RoleColorToken | null;
  /** True if the actor was a team member and has been soft-removed. */
  actor_removed: boolean;
  action: string;
  model_type: string | null;
  model_id: number | null;
  summary: Record<string, unknown>;
  created_at: string | null;
}

export type ActivityPeriod = 'today' | 'week' | 'month';

export interface ActivityFilters {
  q?: string;
  actor_type?: 'member' | 'owner';
  actor_id?: number;
  action?: string;
  period?: ActivityPeriod;
  from?: string;
  to?: string;
  page?: number;
}
