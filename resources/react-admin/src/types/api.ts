export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
  };
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
}

export interface SingleResponse<T> {
  data: T;
}

export interface ErrorResponse {
  message: string;
  errors?: Record<string, string[]>;
}

export type Guard = 'admin' | 'clinic' | 'web';

/**
 * Acting overlay shipped only for `guard === 'clinic'`. Describes who
 * is currently using the panel — owner (Clinic itself) or a team
 * member acting on its behalf. The clinic's identity stays in `user`
 * so older callers that read `user.id` / `user.name` are not broken.
 */
export interface ClinicActing {
  type: 'owner' | 'member';
  id: number | null;
  name: string | null;
  role: 'owner' | 'coordinator' | 'reception';
  is_owner: boolean;
}

export interface CurrentUserResponse {
  data: {
    guard: Guard;
    user: {
      id: number;
      name: string | null;
      email: string | null;
      phone: string | null;
      role: string | null;
      /** Only populated when guard === 'clinic' — the clinic's public slug. */
      slug?: string | null;
    };
    permissions: Record<string, boolean>;
    impersonating: boolean;
    /** Only present when guard === 'clinic'. */
    acting?: ClinicActing;
    /**
     * Subscription feature flags (clinic guard only). Drives hiding of
     * gated surfaces like the CRM tab. Absent → treat every feature as
     * enabled so non-clinic guards / older payloads don't lose access.
     */
    features?: Record<string, boolean>;
  };
}
