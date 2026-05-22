import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicServicesApi, type ClinicServiceFormValues, type ListParams } from './api';

const KEY = ['clinic', 'services'] as const;

export function useClinicServices(params: ListParams = {}) {
  return useQuery({ queryKey: [...KEY, 'list', params], queryFn: () => clinicServicesApi.list(params) });
}

export function useCreateClinicService() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: ClinicServiceFormValues) => clinicServicesApi.create(v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateClinicService(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: Partial<ClinicServiceFormValues>) => clinicServicesApi.update(id, v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteClinicService() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => clinicServicesApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useReorderClinicServices() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (order: { id: number; sort_order: number }[]) => clinicServicesApi.reorder(order),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
