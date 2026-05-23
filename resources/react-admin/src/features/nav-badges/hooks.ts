import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';

export interface AdminNavBadges {
  complaints: number;
  price_quotes: number;
}

export interface ClinicNavBadges {
  price_quotes: number;
  subscription_expiring: number;
  offer_expiring: number;
}

export function useAdminNavBadges() {
  return useQuery({
    queryKey: ['admin', 'nav-badges'],
    queryFn: async () => {
      const res = await apiClient.get<{ data: AdminNavBadges }>('/admin/dashboard/nav-badges');
      return res.data.data;
    },
    refetchInterval: 30_000,
    staleTime: 15_000,
  });
}

export function useClinicNavBadges() {
  return useQuery({
    queryKey: ['clinic', 'nav-badges'],
    queryFn: async () => {
      const res = await apiClient.get<{ data: ClinicNavBadges }>('/clinic/dashboard/nav-badges');
      return res.data.data;
    },
    refetchInterval: 30_000,
    staleTime: 15_000,
  });
}
