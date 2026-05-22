import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { salesLeadsApi, type SalesLeadListParams } from './api/sales-leads.api';
import type { SubscriptionPlan } from './types';

const KEY = ['admin', 'sales-leads'] as const;

export function useSalesLeads(params: SalesLeadListParams = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', params],
    queryFn: () => salesLeadsApi.list(params),
  });
}

export function useDeleteSalesLead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => salesLeadsApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useConvertSalesLead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, plan }: { id: number; plan: SubscriptionPlan }) => salesLeadsApi.convert(id, plan),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEY });
      qc.invalidateQueries({ queryKey: ['admin', 'clinics'] });
    },
  });
}
