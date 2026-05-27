import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { clinicServicesApi, type ClinicServiceFormValues, type ListParams } from './api';

const KEY = ['clinic', 'services'] as const;

export interface SubClinicLookup { id: number; name: string }

export function useSubClinicLookup() {
  return useQuery({
    queryKey: ['clinic', 'lookups', 'sub-clinics'],
    queryFn: async () => {
      const res = await apiClient.get<{ data: SubClinicLookup[] }>('/clinic/lookups/sub-clinics');
      return res.data.data;
    },
    staleTime: 5 * 60_000,
  });
}

export interface ServiceCategoryLookup { id: number; name: string; name_en: string | null; emoji: string | null }

/**
 * Admin-managed service categories — the clinic's "add service" form forces
 * a pick from this list. Shared endpoint (auth: admin,clinic,web) lives at
 * /api/v1/lookups/service-categories so clinics can read it without
 * granting them admin write access.
 */
export function useServiceCategoryLookup() {
  return useQuery({
    queryKey: ['shared', 'lookups', 'service-categories'],
    queryFn: async () => {
      const res = await apiClient.get<{ data: ServiceCategoryLookup[] }>('/lookups/service-categories');
      return res.data.data;
    },
    staleTime: 10 * 60_000,
  });
}

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
