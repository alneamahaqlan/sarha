import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { adminCustomerReportsApi, type UpdateCustomerReportPayload } from './api';

const KEY = ['admin', 'customer-reports'] as const;

export function useAdminCustomerReports(filter: { status?: string; type?: string } = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', filter],
    queryFn: () => adminCustomerReportsApi.list(filter),
  });
}

export function useUpdateAdminCustomerReport(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: UpdateCustomerReportPayload) => adminCustomerReportsApi.update(id, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
