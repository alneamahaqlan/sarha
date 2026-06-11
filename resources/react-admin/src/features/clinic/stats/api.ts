import { apiClient } from '@/lib/api-client';

/** Quick period (days) or an explicit custom range. */
export type StatsRange = { period: number } | { from: string; to: string };

/** Server-side impression sources. AI is excluded from the clinic view. */
export type ImpressionSource = 'search' | 'filter' | 'home' | 'similar_services' | 'compare' | 'profile' | 'ai';

export interface StatsSummary {
  /** Back-compat alias: equal to `impressions_total`. */
  search_appearances: number;
  /** All-source total — INCLUDES AI even when the breakdown hides it. */
  impressions_total: number;
  /**
   * Per-source breakdown. Keys vary by audience:
   *  - clinic-facing payload omits `ai` (the row is hidden by spec)
   *  - admin-facing payload includes all 6
   * The headline `impressions_total` may exceed the sum of these values
   * when `ai` is hidden — that's the deliberate "silent contribution".
   */
  impressions: Partial<Record<ImpressionSource, number>>;
  /** Distinct visitors (de-duplicated per 3-hour window) — the headcount behind impressions. */
  unique_views: number;
  page_views: number;
  bookings: number;
  quote_requests: number;
  whatsapp_clicks: number;
  call_clicks: number;
  directions_clicks: number;
  booking_clicks: number;
  conversion_rate: number;
  /** Realized service income = SUM(net_price) of completed bookings in range. */
  service_income: number;
  completed_bookings: number;
  /** Average value per completed booking. */
  avg_ticket: number;
  /** Count of service line items taken on completed bookings. */
  services_taken: number;
  /** Distinct customers who took at least one service (completed). */
  customers_served: number;
  /** Net value of services on cancelled/no-show bookings (lost revenue). */
  lost_income: number;
  lost_services: number;
  /** Net value + count of services on still-open bookings (booked, not yet taken). */
  pending_income: number;
  pending_services: number;
}

/** One row in the "top services by impressions" table. */
export interface StatsServiceImpressionRow {
  service_id: number;
  name: string;
  total: number;
  by_source: Partial<Record<ImpressionSource, number>>;
}

export interface StatsComparison {
  rank: number | null;
  total: number;
  clinic_bookings: number;
  avg_bookings: number;
  clinic_visits: number;
  avg_visits: number;
  clinic_appearances: number;
  avg_appearances: number;
  bookings_vs_avg_pct: number | null;
  visits_vs_avg_pct: number | null;
  appearances_vs_avg_pct: number | null;
}

export interface StatsTrendPoint {
  date: string;
  search_appearances: number;
  unique_views: number;
  page_views: number;
  bookings: number;
  quote_requests: number;
  income: number;
}

export interface StatsServiceRow {
  id: number;
  name: string;
  price: number;
  bookings: number;
  income: number;
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
  bookings_by_acquisition_source: Record<string, number>;
  income_by_acquisition_source: Record<string, number>;
  quotes_by_status: Record<string, number>;
  quotes_top_services: { name: string; count: number }[];
  services_performance: StatsServiceRow[];
  top_services_by_views: StatsServiceImpressionRow[];
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
