import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { categoriesApi, type CategoryListParams, type ReorderPayload } from './api/categories.api';
import type { CategoryFormValues } from './types';

const KEY = ['admin', 'categories'] as const;

export function useCategories(params: CategoryListParams = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', params],
    queryFn: () => categoriesApi.list(params),
  });
}

export function useCategory(id: number | null) {
  return useQuery({
    queryKey: [...KEY, 'detail', id],
    queryFn: () => categoriesApi.get(id as number),
    enabled: id !== null,
  });
}

export function useCreateCategory() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: CategoryFormValues) => categoriesApi.create(values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateCategory(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<CategoryFormValues>) => categoriesApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteCategory() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => categoriesApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useReorderCategories() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: ReorderPayload) => categoriesApi.reorder(payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
