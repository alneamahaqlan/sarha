import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicCategoriesApi, type ClinicCategoryFormValues } from './api';

const KEY = ['clinic', 'custom-categories'] as const;

export function useClinicCategories(search?: string) {
  return useQuery({ queryKey: [...KEY, 'list', search ?? ''], queryFn: () => clinicCategoriesApi.list(search) });
}

export function useCreateClinicCategory() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: ClinicCategoryFormValues) => clinicCategoriesApi.create(v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateClinicCategory(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: Partial<ClinicCategoryFormValues>) => clinicCategoriesApi.update(id, v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteClinicCategory() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => clinicCategoriesApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useReorderClinicCategories() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (order: { id: number; sort_order: number }[]) => clinicCategoriesApi.reorder(order),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
