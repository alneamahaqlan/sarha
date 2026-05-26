import { apiClient } from '@/lib/api-client';

export interface ClinicLatestService {
  id: number;
  name: string;
  price: number;
  is_active: boolean;
  created_at: string | null;
}

export interface ClinicVisibility {
  impressions: number;
  visits: number;
  requests: number;
}

export interface ClinicStats {
  total_bookings: number;
  new_bookings: number;
  month_bookings: number;
  active_services: number;
  subscription_type: 'basic' | 'premium' | null;
  subscription_ends_at: string | null;
  is_subscription_active: boolean;
  google_rating: number | null;
  google_reviews_count: number;
  visibility: ClinicVisibility;
  latest_services: ClinicLatestService[];
}

export const clinicDashboardApi = {
  stats: async () => {
    const res = await apiClient.get<{ data: ClinicStats }>('/clinic/dashboard/stats');
    return res.data.data;
  },
};
