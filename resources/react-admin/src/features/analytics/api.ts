import { apiClient } from '@/lib/api-client';

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

export interface AnalyticsData {
  cards: {
    total_views: number;
    contact_requests: number;
    revenue: number;
  };
  monthly: AnalyticsMonthlyRow[];
  by_specialty: AnalyticsSpecialty[];
}

export const analyticsApi = {
  get: async (period = 30) => {
    const res = await apiClient.get<{ data: AnalyticsData }>('/admin/dashboard/analytics', {
      params: { period },
    });
    return res.data.data;
  },
};
