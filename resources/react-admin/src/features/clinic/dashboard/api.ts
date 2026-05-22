import { apiClient } from '@/lib/api-client';

export interface ClinicStats {
  total_bookings: number;
  new_bookings: number;
  month_bookings: number;
  active_services: number;
  subscription_type: 'basic' | 'premium' | null;
  subscription_ends_at: string | null;
  is_subscription_active: boolean;
}

export const clinicDashboardApi = {
  stats: async () => {
    const res = await apiClient.get<{ data: ClinicStats }>('/clinic/dashboard/stats');
    return res.data.data;
  },
};
