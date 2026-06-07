import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { navigationLinksApi, type NavigationLinkListParams, type ReorderPayload } from './api/navigation-links.api';
import type { NavigationLinkFormValues } from './types';

const KEY = ['admin', 'navigation-links'] as const;

export function useNavigationLinks(params: NavigationLinkListParams = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', params],
    queryFn: () => navigationLinksApi.list(params),
  });
}

export function useCreateNavigationLink() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: NavigationLinkFormValues) => navigationLinksApi.create(values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateNavigationLink(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<NavigationLinkFormValues>) => navigationLinksApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteNavigationLink() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => navigationLinksApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useReorderNavigationLinks() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: ReorderPayload) => navigationLinksApi.reorder(payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
