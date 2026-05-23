import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { adminArticlesApi, type AdminArticleFormValues, type ListParams } from './api';

const KEY = ['admin', 'articles'] as const;

export function useAdminArticles(params: ListParams = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', params],
    queryFn: () => adminArticlesApi.list(params),
  });
}

function invalidator(qc: ReturnType<typeof useQueryClient>) {
  return () => qc.invalidateQueries({ queryKey: KEY });
}

export function useCreateAdminArticle() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: AdminArticleFormValues) => adminArticlesApi.create(v),
    onSuccess: invalidator(qc),
  });
}

export function useUpdateAdminArticle(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: Partial<AdminArticleFormValues>) => adminArticlesApi.update(id, v),
    onSuccess: invalidator(qc),
  });
}

export function useDeleteAdminArticle() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => adminArticlesApi.delete(id),
    onSuccess: invalidator(qc),
  });
}
