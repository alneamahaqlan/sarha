import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicAbandonedCartsApi } from './api';

const KEY = ['clinic', 'abandoned-carts'] as const;

export function useClinicAbandonedCarts() {
  return useQuery({ queryKey: KEY, queryFn: () => clinicAbandonedCartsApi.index(), staleTime: 30_000 });
}

export function useContactCart() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ userId, channel }: { userId: number; channel: 'whatsapp' | 'call' | 'manual' }) =>
      clinicAbandonedCartsApi.contact(userId, channel),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useConvertCart() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (userId: number) => clinicAbandonedCartsApi.convert(userId),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
