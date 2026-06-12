import { useQuery } from '@tanstack/react-query';
import { adminAbandonedCartsApi } from './api';

const KEY = ['admin', 'abandoned-carts'] as const;

export function useAbandonedCarts() {
  return useQuery({ queryKey: KEY, queryFn: () => adminAbandonedCartsApi.index(), staleTime: 30_000 });
}

export function useAbandonedCartDetail(clinicId: number) {
  return useQuery({
    queryKey: [...KEY, clinicId],
    queryFn: () => adminAbandonedCartsApi.show(clinicId),
    enabled: Number.isFinite(clinicId) && clinicId > 0,
  });
}
