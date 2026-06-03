import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { catalogServicesApi } from './api';

const KEY = ['admin', 'catalog-services'] as const;

export function useCatalogServices(params: { status?: string; page?: number; search?: string } = {}) {
  return useQuery({
    queryKey: [...KEY, params],
    queryFn: () => catalogServicesApi.list(params),
  });
}

export function useUpdateCatalogService() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, ...payload }: { id: number; name?: string; name_en?: string | null; aliases?: string[] }) =>
      catalogServicesApi.update(id, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useApproveCatalogService() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => catalogServicesApi.approve(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useRejectCatalogService() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => catalogServicesApi.reject(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
