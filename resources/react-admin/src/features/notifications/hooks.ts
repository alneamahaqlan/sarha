import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { notificationsApi } from './api';

const KEY = ['notifications'] as const;

export function useNotifications() {
  return useQuery({
    queryKey: [...KEY, 'list'],
    queryFn: () => notificationsApi.list(),
    // Live bell: poll every 15s AND keep polling while the tab is in the
    // background, so unread count is already fresh when the admin returns.
    // 'always' refetch on focus guarantees an immediate refresh on tab switch
    // (overrides the global refetchOnWindowFocus: false). Previously polling
    // paused in the background + focus never refetched, so the bell looked
    // like it only updated on click.
    refetchInterval: 15_000,
    refetchIntervalInBackground: true,
    refetchOnWindowFocus: 'always',
    staleTime: 10_000,
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
