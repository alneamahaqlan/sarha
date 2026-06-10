import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { adminQuoteAccessApi } from './api';

const KEY = ['admin', 'price-quote-access'] as const;

export function useQuoteAccessRequests(status: string, search: string) {
  return useQuery({
    queryKey: [...KEY, 'requests', status, search],
    queryFn: () => adminQuoteAccessApi.requests(status, search),
  });
}

export function useApproveQuoteAccess() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => adminQuoteAccessApi.approve(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useRejectQuoteAccess() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => adminQuoteAccessApi.reject(id, reason),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDisableQuoteAccess() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => adminQuoteAccessApi.disable(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
