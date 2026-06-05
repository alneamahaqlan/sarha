import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { salesLeadsApi, type SalesLeadListParams } from './api/sales-leads.api';
import type { ConvertLeadPayload, LeadActivityType, SalesLeadFormValues } from './types';

const KEY = ['admin', 'sales-leads'] as const;

export function useSalesLeads(params: SalesLeadListParams = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', params],
    queryFn: () => salesLeadsApi.list(params),
  });
}

export function useCreateSalesLead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: SalesLeadFormValues) => salesLeadsApi.create(values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateSalesLead(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<SalesLeadFormValues>) => salesLeadsApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteSalesLead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => salesLeadsApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useLeadActivities(id: number | null) {
  return useQuery({
    enabled: !!id,
    queryKey: [...KEY, 'activities', id],
    queryFn: () => salesLeadsApi.activities(id!),
    staleTime: 10_000,
  });
}

export function useLogLeadActivity(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: { type: LeadActivityType; body?: string | null }) =>
      salesLeadsApi.logActivity(id, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [...KEY, 'activities', id] });
      // last_contact_at + score may shift → refresh the list too.
      qc.invalidateQueries({ queryKey: [...KEY, 'list'] });
    },
  });
}

export function useConvertSalesLead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: ConvertLeadPayload }) => salesLeadsApi.convert(id, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEY });
      qc.invalidateQueries({ queryKey: ['admin', 'clinics'] });
    },
  });
}
