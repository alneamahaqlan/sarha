import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicsApi, type ClinicFormValues, type ClinicListParams, type ClinicStatsPeriod } from './api/clinics.api';

const KEY = ['admin', 'clinics'] as const;

export function useClinics(params: ClinicListParams = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', params],
    queryFn: () => clinicsApi.list(params),
  });
}

export function useClinic(id: number | undefined) {
  return useQuery({
    queryKey: [...KEY, 'detail', id],
    queryFn: () => clinicsApi.get(id!),
    enabled: !!id,
    staleTime: 5_000,
  });
}

export function useClinicStats(id: number | undefined, period: ClinicStatsPeriod) {
  return useQuery({
    queryKey: [...KEY, 'stats', id, period],
    queryFn: () => clinicsApi.stats(id!, period),
    enabled: !!id,
    staleTime: 60_000,
  });
}

function invalidator(qc: ReturnType<typeof useQueryClient>) {
  return () => qc.invalidateQueries({ queryKey: KEY });
}

export function useCreateClinic() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: ClinicFormValues) => clinicsApi.create(v),
    onSuccess: invalidator(qc),
  });
}

export function useUpdateClinic(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: Partial<ClinicFormValues>) => clinicsApi.update(id, v),
    onSuccess: invalidator(qc),
  });
}

export function useApproveClinic() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: (id: number) => clinicsApi.approve(id), onSuccess: invalidator(qc) });
}

export function useRejectClinic() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => clinicsApi.reject(id, reason),
    onSuccess: invalidator(qc),
  });
}

export function useActivateClinic() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: (id: number) => clinicsApi.activate(id), onSuccess: invalidator(qc) });
}

export function useSuspendClinic() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: (id: number) => clinicsApi.suspend(id), onSuccess: invalidator(qc) });
}

export function useExtendClinic() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, days }: { id: number; days: 30 | 90 }) => clinicsApi.extend(id, days),
    onSuccess: invalidator(qc),
  });
}

export function useImpersonateClinic() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => clinicsApi.impersonate(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['auth', 'me'] }),
  });
}

export function useDeleteClinic() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: (id: number) => clinicsApi.delete(id), onSuccess: invalidator(qc) });
}

export function useRestoreClinic() {
  const qc = useQueryClient();
  return useMutation({ mutationFn: (id: number) => clinicsApi.restore(id), onSuccess: invalidator(qc) });
}

export function useBulkClinic() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ action, ids }: { action: 'delete' | 'restore' | 'force_delete'; ids: number[] }) =>
      clinicsApi.bulk(action, ids),
    onSuccess: invalidator(qc),
  });
}

export function useImportClinicsSheet() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (url: string) => clinicsApi.importSheet(url),
    onSuccess: invalidator(qc),
  });
}
