import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { adminCartApi } from './api';

const KEY = ['admin', 'cart'] as const;

export function useCartRequests(status: string) {
  return useQuery({
    queryKey: [...KEY, 'requests', status],
    queryFn: () => adminCartApi.requests(status),
  });
}

export function useApproveCart() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => adminCartApi.approve(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useRejectCart() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => adminCartApi.reject(id, reason),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDisableCart() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => adminCartApi.disable(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
