import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { serviceCategoriesApi, type ReorderPayload, type ServiceCategoryListParams } from './api/service-categories.api';
import type { ServiceCategoryFormValues } from './types';

const KEY = ['admin', 'service-categories'] as const;

export function useServiceCategories(params: ServiceCategoryListParams = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', params],
    queryFn: () => serviceCategoriesApi.list(params),
  });
}

export function useServiceCategory(id: number | null) {
  return useQuery({
    queryKey: [...KEY, 'detail', id],
    queryFn: () => serviceCategoriesApi.get(id as number),
    enabled: id !== null,
  });
}

export function useCreateServiceCategory() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: ServiceCategoryFormValues) => serviceCategoriesApi.create(values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateServiceCategory(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<ServiceCategoryFormValues>) => serviceCategoriesApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteServiceCategory() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => serviceCategoriesApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useReorderServiceCategories() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: ReorderPayload) => serviceCategoriesApi.reorder(payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
