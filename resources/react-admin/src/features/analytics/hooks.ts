import { useQuery } from '@tanstack/react-query';
import { analyticsApi, type StatsRange } from './api';

const KEY = ['admin', 'analytics'] as const;

export function useAnalytics(range: StatsRange) {
  return useQuery({
    queryKey: [...KEY, range],
    queryFn: () => analyticsApi.get(range),
    staleTime: 60_000,
  });
}
