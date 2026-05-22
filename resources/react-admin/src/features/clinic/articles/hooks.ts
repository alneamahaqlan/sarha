import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicArticlesApi, type ClinicArticleFormValues, type ListParams } from './api';

const KEY = ['clinic', 'articles'] as const;

export function useClinicArticles(params: ListParams = {}) {
  return useQuery({ queryKey: [...KEY, 'list', params], queryFn: () => clinicArticlesApi.list(params) });
}

export function useClinicArticle(id: number | null) {
  return useQuery({
    queryKey: [...KEY, 'detail', id],
    queryFn: () => clinicArticlesApi.get(id as number),
    enabled: id !== null,
  });
}

export function useCreateClinicArticle() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: ClinicArticleFormValues) => clinicArticlesApi.create(v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateClinicArticle(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: Partial<ClinicArticleFormValues>) => clinicArticlesApi.update(id, v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteClinicArticle() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => clinicArticlesApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useGenerateArticleAi() {
  return useMutation({
    mutationFn: ({ kind, title }: { kind: 'excerpt' | 'article'; title: string }) =>
      clinicArticlesApi.generateAi(kind, title),
  });
}
