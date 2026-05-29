import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicReportsApi, type ClinicReportFormValues } from './api';

const KEY = ['clinic', 'reports'] as const;

export function useClinicReports(status?: string) {
  return useQuery({
    queryKey: [...KEY, 'list', status ?? null],
    queryFn: () => clinicReportsApi.list(status),
  });
}

export function useCreateClinicReport() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: ClinicReportFormValues) => clinicReportsApi.create(v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
