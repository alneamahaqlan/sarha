import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicComplaintsApi, type ClinicComplaintFormValues } from './api';

const KEY = ['clinic', 'complaints'] as const;

export function useClinicComplaints(status?: string) {
  return useQuery({
    queryKey: [...KEY, 'list', status ?? null],
    queryFn: () => clinicComplaintsApi.list(status),
  });
}

export function useCreateClinicComplaint() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: ClinicComplaintFormValues) => clinicComplaintsApi.create(v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
