import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicCartApi } from './api';

const KEY = ['clinic', 'cart'] as const;

export function useClinicCart() {
  return useQuery({ queryKey: KEY, queryFn: clinicCartApi.show });
}

export function useUpdateClinicCart() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: { cart_storefront_enabled: boolean }) => clinicCartApi.update(v),
    onSuccess: (data) => qc.setQueryData(KEY, data),
  });
}

export function useRequestClinicCart() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => clinicCartApi.request(),
    onSuccess: (data) => qc.setQueryData(KEY, data),
  });
}
