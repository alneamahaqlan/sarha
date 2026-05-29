import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { adminClinicReportsApi, type UpdateReportPayload } from './api';

const KEY = ['admin', 'clinic-reports'] as const;

export function useAdminClinicReports(filter: { status?: string; type?: string } = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', filter],
    queryFn: () => adminClinicReportsApi.list(filter),
  });
}

export function useUpdateAdminClinicReport(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: UpdateReportPayload) => adminClinicReportsApi.update(id, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
