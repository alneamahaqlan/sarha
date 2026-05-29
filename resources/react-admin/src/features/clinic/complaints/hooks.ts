import { useQuery } from '@tanstack/react-query';
import { clinicComplaintsApi } from './api';

const KEY = ['clinic', 'complaints'] as const;

export function useClinicComplaints(status?: string) {
  return useQuery({
    queryKey: [...KEY, 'list', status ?? null],
    queryFn: () => clinicComplaintsApi.list(status),
  });
}
