import { useQuery } from '@tanstack/react-query';
import { clinicStatsApi, type StatsRange } from './api';

export function useMyStats(range: StatsRange) {
  return useQuery({
    queryKey: ['clinic', 'stats', range],
    queryFn: () => clinicStatsApi.mine(range),
    staleTime: 60_000,
  });
}

export function useClinicStatsFull(clinicId: number | undefined, range: StatsRange) {
  return useQuery({
    queryKey: ['admin', 'clinic-stats', clinicId, range],
    queryFn: () => clinicStatsApi.forClinic(clinicId as number, range),
    enabled: clinicId !== undefined,
    staleTime: 60_000,
  });
}
