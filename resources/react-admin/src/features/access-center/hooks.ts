import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { accessCenterApi, type GateAction } from './api';

const KEY = ['admin', 'access-center'] as const;

export function useAccessMeta() {
  return useQuery({ queryKey: [...KEY, 'meta'], queryFn: () => accessCenterApi.meta(), staleTime: 5 * 60_000 });
}

export function useAccessPending() {
  return useQuery({ queryKey: [...KEY, 'pending'], queryFn: () => accessCenterApi.pending() });
}

export function useAccessClinics(search: string) {
  return useQuery({ queryKey: [...KEY, 'clinics', search], queryFn: () => accessCenterApi.clinics(search) });
}

export function useAccessPackages() {
  return useQuery({ queryKey: [...KEY, 'packages'], queryFn: () => accessCenterApi.packages(), staleTime: 5 * 60_000 });
}

export function useGateAction() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: { gate: string; clinicId: number; action: GateAction; reason?: string }) =>
      accessCenterApi.action(v.gate, v.clinicId, v.action, v.reason),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
