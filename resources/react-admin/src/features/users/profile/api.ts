import { apiClient } from '@/lib/api-client';

export interface ProfileHeader {
  user: {
    id: number;
    name: string;
    initials: string;
    phone: string | null;
    phone_masked: boolean;
    email: string | null;
    email_masked: boolean;
    is_active: boolean;
    created_at: string | null;
  };
  badges: {
    member_since_days: number;
    bookings_count: number;
    complaints_count: number;
    engagement_level: 'active' | 'medium' | 'idle';
  };
  summary_cards: {
    sessions_total: number;
    time_on_platform_seconds: number;
    last_login_at: string | null;
    last_login_user_agent: string | null;
    last_login_ip: string | null;
    avg_session_seconds: number;
    top_category: string | null;
    top_city: string | null;
  };
  insights: {
    engagement_level: 'active' | 'medium' | 'idle';
    decision_stage: 'exploration' | 'comparing' | 'near_decision' | null;
    preferred_city: string | null;
    preferred_category: string | null;
  };
  risk_flags: { code: string; count: number }[];
}

export interface TimelineEvent {
  id: string;
  type: string;
  title: string;
  details: Record<string, unknown> | null;
  related_clinic_id: number | null;
  related_url: string | null;
  occurred_at: string;
}

export interface Paginated<T> {
  rows: T[];
  meta: { total: number; per_page: number; page: number; last_page: number };
}

export const userProfileApi = {
  /** Header + summary cards + insights + risk flags. Triggers an audit-log entry server-side. */
  show: async (userId: number, reveal = false): Promise<ProfileHeader> => {
    const res = await apiClient.get<{ data: ProfileHeader }>(`/admin/users/${userId}/profile`, {
      params: reveal ? { reveal: 1 } : undefined,
    });
    return res.data.data;
  },
  timeline: async (
    userId: number,
    params: { page?: number; per_page?: number; types?: string[]; from?: string; to?: string; clinic_id?: number; q?: string },
  ) => {
    const res = await apiClient.get<{ data: TimelineEvent[]; meta: { total: number; per_page: number; page: number; last_page: number } }>(
      `/admin/users/${userId}/profile/timeline`,
      { params },
    );
    return res.data;
  },
  hours: async (userId: number): Promise<number[][]> => {
    const res = await apiClient.get<{ data: { matrix: number[][] } }>(`/admin/users/${userId}/profile/hours`);
    return res.data.data.matrix;
  },
  tab: async <T>(userId: number, tab: string, page = 1, perPage = 15) => {
    const res = await apiClient.get<{ data: Paginated<T> & { latest_summary?: unknown } }>(
      `/admin/users/${userId}/profile/tab/${tab}`,
      { params: { page, per_page: perPage } },
    );
    return res.data.data;
  },
  suspend: async (userId: number) => {
    const res = await apiClient.post<{ data: { is_active: boolean } }>(`/admin/users/${userId}/suspend`);
    return res.data.data;
  },
  forceLogout: async (userId: number) => {
    const res = await apiClient.post<{ data: { ok: boolean } }>(`/admin/users/${userId}/force-logout`);
    return res.data.data;
  },
};

export function formatDuration(totalSeconds: number, locale: 'ar' | 'en' = 'ar'): string {
  if (!totalSeconds || totalSeconds < 0) return locale === 'ar' ? '٠ دقيقة' : '0 min';
  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  if (locale === 'ar') {
    if (hours === 0) return `${minutes} دقيقة`;
    return `${hours} ساعة ${minutes} دقيقة`;
  }
  if (hours === 0) return `${minutes} min`;
  return `${hours}h ${minutes}m`;
}
