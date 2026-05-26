import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicBeforeAfterApi, type BeforeAfterFormValues } from './api';

const KEY = ['clinic', 'before-after'] as const;

export function useClinicBeforeAfter() {
  return useQuery({ queryKey: KEY, queryFn: () => clinicBeforeAfterApi.list() });
}

export function useCreateBeforeAfter() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: BeforeAfterFormValues) => clinicBeforeAfterApi.create(v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateBeforeAfter(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: Partial<BeforeAfterFormValues>) => clinicBeforeAfterApi.update(id, v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteBeforeAfter() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => clinicBeforeAfterApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
