import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { badgesApi } from './api';
import type { BadgeFormValues } from './types';

const KEY = ['badges'] as const;

export function useBadges() {
  return useQuery({ queryKey: KEY, queryFn: badgesApi.list });
}

export function useBadgeMeta() {
  return useQuery({ queryKey: ['badges', 'meta'], queryFn: badgesApi.meta, staleTime: 5 * 60_000 });
}

export function useCreateBadge() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: BadgeFormValues) => badgesApi.create(v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateBadge(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: BadgeFormValues) => badgesApi.update(id, v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteBadge() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => badgesApi.remove(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useRecomputeBadges() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => badgesApi.recompute(),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
