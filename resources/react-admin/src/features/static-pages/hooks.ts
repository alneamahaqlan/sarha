import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { staticPagesApi, type StaticPageListParams, type ReorderPayload } from './api/static-pages.api';
import type { StaticPageFormValues } from './types';

const KEY = ['admin', 'static-pages'] as const;

export function useStaticPages(params: StaticPageListParams = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', params],
    queryFn: () => staticPagesApi.list(params),
  });
}

export function useStaticPage(id: number | null) {
  return useQuery({
    queryKey: [...KEY, 'detail', id],
    queryFn: () => staticPagesApi.get(id as number),
    enabled: id !== null,
  });
}

export function useCreateStaticPage() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: StaticPageFormValues) => staticPagesApi.create(values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateStaticPage(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<StaticPageFormValues>) => staticPagesApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteStaticPage() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => staticPagesApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useReorderStaticPages() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: ReorderPayload) => staticPagesApi.reorder(payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
