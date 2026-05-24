import { apiClient } from '@/lib/api-client';

/** Quick period (days) or an explicit custom range. */
export type StatsRange = { period: number } | { from: string; to: string };

export interface StatsSummary {
  search_appearances: number;
  page_views: number;
  bookings: number;
  quote_requests: number;
  whatsapp_clicks: number;
  call_clicks: number;
  booking_clicks: number;
  conversion_rate: number;
}

export interface StatsComparison {
  rank: number | null;
  total: number;
  clinic_bookings: number;
  avg_bookings: number;
  clinic_visits: number;
  avg_visits: number;
  bookings_vs_avg_pct: number | null;
  visits_vs_avg_pct: number | null;
}

export interface StatsTrendPoint {
  date: string;
  search_appearances: number;
  page_views: number;
  bookings: number;
  quote_requests: number;
}

export interface StatsServiceRow {
  id: number;
  name: string;
  price: number;
  bookings: number;
  quote_requests: number;
}

export interface StatsArticleRow {
  id: number;
  title: string;
  slug: string;
  views: number;
  ai_generated: boolean;
}

export interface ClinicStatsFull {
  period: { from: string; to: string; days: number };
  summary: StatsSummary;
  comparison: StatsComparison;
  trend: StatsTrendPoint[];
  bookings_by_status: Record<string, number>;
  bookings_by_source: Record<string, number>;
  quotes_by_status: Record<string, number>;
  quotes_top_services: { name: string; count: number }[];
  services_performance: StatsServiceRow[];
  articles_performance: StatsArticleRow[];
  best_days: { top_visits_weekday: number | null; top_requests_weekday: number | null };
  recommendation: string;
}

export function rangeToParams(range: StatsRange): Record<string, string | number> {
  return 'period' in range ? { period: range.period } : { from: range.from, to: range.to };
}

export const clinicStatsApi = {
  /** Logged-in clinic's own stats. */
  mine: async (range: StatsRange) => {
    const res = await apiClient.get<{ data: ClinicStatsFull }>('/clinic/stats', { params: rangeToParams(range) });
    return res.data.data;
  },
  /** Admin viewing a specific clinic's stats. */
  forClinic: async (clinicId: number, range: StatsRange) => {
    const res = await apiClient.get<{ data: ClinicStatsFull }>(`/admin/clinics/${clinicId}/stats`, {
      params: rangeToParams(range),
    });
    return res.data.data;
  },
};
