import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { adminTrackingApi } from './api';

const KEY = ['admin', 'tracking'] as const;

export function useTrackingSettings() {
  return useQuery({ queryKey: [...KEY, 'settings'], queryFn: adminTrackingApi.settings });
}

export function useUpdateTrackingSettings() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: adminTrackingApi.updateSettings,
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useTrackingRequests(status: string) {
  return useQuery({
    queryKey: [...KEY, 'requests', status],
    queryFn: () => adminTrackingApi.requests(status),
  });
}

export function useApproveTracking() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => adminTrackingApi.approve(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useRejectTracking() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => adminTrackingApi.reject(id, reason),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDisableTracking() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => adminTrackingApi.disable(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
