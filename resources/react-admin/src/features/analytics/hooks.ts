import { useQuery } from '@tanstack/react-query';
import { analyticsApi } from './api';

const KEY = ['admin', 'analytics'] as const;

export function useAnalytics(period = 30) {
  return useQuery({
    queryKey: [...KEY, period],
    queryFn: () => analyticsApi.get(period),
    staleTime: 60_000,
  });
}
