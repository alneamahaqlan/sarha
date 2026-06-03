import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { salesLeadsApi, type SalesLeadListParams } from './api/sales-leads.api';
import type { ConvertLeadPayload, SalesLeadFormValues } from './types';

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
