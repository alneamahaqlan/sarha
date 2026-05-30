import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { subscriptionsApi, type SubscriptionListParams } from './api/subscriptions.api';
import type { SubscriptionFormValues } from './types';

const KEY = ['admin', 'subscriptions'] as const;

export function useSubscriptions(params: SubscriptionListParams = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', params],
    queryFn: () => subscriptionsApi.list(params),
  });
}

export function useCreateSubscription() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: SubscriptionFormValues) => subscriptionsApi.create(values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateSubscription(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<SubscriptionFormValues>) => subscriptionsApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

/** One-click renew — chains a new sub on the same package + cycle. */
export function useRenewSubscription() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => subscriptionsApi.renew(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

/** Cancel today; the row stays in history with status='cancelled'. */
export function useCancelSubscription() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => subscriptionsApi.cancel(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
