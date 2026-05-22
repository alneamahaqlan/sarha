import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { notificationsApi } from './api';

const KEY = ['notifications'] as const;

export function useNotifications() {
  return useQuery({
    queryKey: [...KEY, 'list'],
    queryFn: () => notificationsApi.list(),
    refetchInterval: 30_000,
    staleTime: 15_000,
  });
}

export function useMarkRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => notificationsApi.markRead(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useMarkAllRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => notificationsApi.markAllRead(),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
