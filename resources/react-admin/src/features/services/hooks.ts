import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { servicesApi, type ServiceListParams } from './api/services.api';
import type { ServiceFormValues } from './types';

const KEY = ['admin', 'services'] as const;

export function useServices(params: ServiceListParams = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', params],
    queryFn: () => servicesApi.list(params),
  });
}

export function useCreateService() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: ServiceFormValues) => servicesApi.create(values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateService(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<ServiceFormValues>) => servicesApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteService() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => servicesApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
