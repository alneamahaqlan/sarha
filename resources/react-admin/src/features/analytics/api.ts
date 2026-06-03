import { apiClient } from '@/lib/api-client';
import { type StatsRange, type ImpressionSource, rangeToParams } from '@/features/clinic/stats/api';

export type { StatsRange, ImpressionSource };

export interface AnalyticsMonthlyRow {
  month: string;
  clinics: number;
  bookings: number;
  revenue: number;
}

export interface AnalyticsSpecialty {
  name: string;
  clinics_count: number;
}

export interface AnalyticsSummary {
  impressions_total: number;
  impressions: Partial<Record<ImpressionSource, number>>;
  page_views: number;
  bookings: number;
  quote_requests: number;
  whatsapp_clicks: number;
  call_clicks: number;
  directions_clicks: number;
  booking_clicks: number;
  conversion_rate: number;
  /** page views as a share of impressions */
  engagement_rate: number;
}

export interface AnalyticsPlatform {
  revenue: number;
  new_subscriptions: number;
  active_subscriptions: number;
  new_clinics: number;
  active_clinics: number;
  total_clinics: number;
}

/** Percentage change vs the immediately preceding window; null when no baseline. */
export interface AnalyticsDeltas {
  impressions: number | null;
  page_views: number | null;
  bookings: number | null;
  quote_requests: number | null;
  revenue: number | null;
}

export interface AnalyticsFunnel {
  impressions: number;
  page_views: number;
  contacts: number;
  conversions: number;
  view_rate: number;
  contact_rate: number;
  close_rate: number;
}

export interface AnalyticsTrendPoint {
  date: string;
  impressions: number;
  page_views: number;
  bookings: number;
  quote_requests: number;
}

export interface AnalyticsSourceRow {
  source: ImpressionSource;
  count: number;
  pct: number;
}

export interface AnalyticsTopClinic {
  id: number;
  name: string;
  impressions: number;
  page_views: number;
  bookings: number;
  quote_requests: number;
  conversion_rate: number;
}

export interface AnalyticsTopService {
  service_id: number;
  name: string;
  clinic_name: string;
  total: number;
  by_source: Partial<Record<ImpressionSource, number>>;
}

export interface AnalyticsData {
  period: { from: string; to: string; days: number };
  summary: AnalyticsSummary;
  platform: AnalyticsPlatform;
  deltas: AnalyticsDeltas;
  funnel: AnalyticsFunnel;
  trend: AnalyticsTrendPoint[];
  impressions_by_source: AnalyticsSourceRow[];
  bookings_by_status: Record<string, number>;
  quotes_by_status: Record<string, number>;
  bookings_by_source: Record<string, number>;
  best_days: { top_visits_weekday: number | null; top_requests_weekday: number | null };
  top_clinics: AnalyticsTopClinic[];
  top_services: AnalyticsTopService[];
  by_specialty: AnalyticsSpecialty[];
  monthly: AnalyticsMonthlyRow[];
}

export const analyticsApi = {
  get: async (range: StatsRange) => {
    const res = await apiClient.get<{ data: AnalyticsData }>('/admin/dashboard/analytics', {
      params: rangeToParams(range),
    });
    return res.data.data;
  },
};
