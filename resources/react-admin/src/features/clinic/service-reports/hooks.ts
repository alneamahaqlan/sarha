import { useQuery } from '@tanstack/react-query';
import { serviceReportsApi } from './api';
import type { DateRange } from './types';

const KEY = ['clinic', 'service-reports'] as const;

export function useBestSelling(range: DateRange) {
  return useQuery({
    queryKey: [...KEY, 'best-selling', range],
    queryFn: () => serviceReportsApi.bestSelling(range),
    staleTime: 30_000,
  });
}

export function useMostInterested(range: DateRange) {
  return useQuery({
    queryKey: [...KEY, 'most-interested', range],
    queryFn: () => serviceReportsApi.mostInterested(range),
    staleTime: 30_000,
  });
}

export function useInterestedCustomers(serviceId: number | null) {
  return useQuery({
    enabled: !!serviceId,
    queryKey: [...KEY, 'interested-customers', serviceId],
    queryFn: () => serviceReportsApi.interestedCustomers(serviceId!),
  });
}

export function useServiceBuyers(serviceId: number | null, range: DateRange) {
  return useQuery({
    enabled: !!serviceId,
    queryKey: [...KEY, 'buyers', serviceId, range],
    queryFn: () => serviceReportsApi.buyers(serviceId!, range),
  });
}
