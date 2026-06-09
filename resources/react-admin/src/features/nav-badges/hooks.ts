import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';

export interface AdminNavBadges {
  complaints: number;
  price_quotes: number;
  clinics_pending: number;
  subscriptions_expiring: number;
  category_requests: number;
  catalog_requests: number;
  clinic_reports: number;
  customer_reports: number;
  tracking_pending: number;
  cart_pending: number;
  sales_followups_overdue: number;
}

export interface ClinicNavBadges {
  price_quotes: number;
  subscription_expiring: number;
  offer_expiring: number;
  complaints: number;
  reminders_overdue: number;
}

export function useAdminNavBadges() {
  return useQuery({
    queryKey: ['admin', 'nav-badges'],
    queryFn: async () => {
      const res = await apiClient.get<{ data: AdminNavBadges }>('/admin/dashboard/nav-badges');
      return res.data.data;
    },
    // Keep nav badges live alongside the bell (see useNotifications).
    refetchInterval: 15_000,
    refetchIntervalInBackground: true,
    refetchOnWindowFocus: true,
    staleTime: 10_000,
  });
}

export function useClinicNavBadges() {
  return useQuery({
    queryKey: ['clinic', 'nav-badges'],
    queryFn: async () => {
      const res = await apiClient.get<{ data: ClinicNavBadges }>('/clinic/dashboard/nav-badges');
      return res.data.data;
    },
    refetchInterval: 15_000,
    refetchIntervalInBackground: true,
    refetchOnWindowFocus: true,
    staleTime: 10_000,
  });
}
