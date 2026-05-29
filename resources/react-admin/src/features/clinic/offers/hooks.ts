import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicOffersApi } from './api';
import type { OfferFormValues, OfferStatus } from './types';

const KEY = ['clinic', 'offers'] as const;

export function useClinicOffers(status?: OfferStatus) {
  return useQuery({
    queryKey: [...KEY, 'list', status ?? 'all'],
    queryFn: () => clinicOffersApi.list(status),
  });
}

export function useCreateClinicOffer() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: OfferFormValues) => clinicOffersApi.create(values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateClinicOffer(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<OfferFormValues>) => clinicOffersApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteClinicOffer() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => clinicOffersApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useExtendClinicOffer() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, days }: { id: number; days: number }) => clinicOffersApi.extend(id, days),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
