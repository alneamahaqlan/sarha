import { useQuery } from '@tanstack/react-query';
import { clinicDashboardApi } from './api';

export function useClinicStats() {
  return useQuery({
    queryKey: ['clinic', 'dashboard', 'stats'],
    queryFn: () => clinicDashboardApi.stats(),
    staleTime: 60_000,
  });
}
