import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicPageBuilderApi, type ReorderPayload } from './api';
import type { ClinicPageSectionFormValues } from './types';

const KEY = ['clinic', 'page-sections'] as const;

export function useClinicPageSections() {
  return useQuery({
    queryKey: [...KEY, 'list'],
    queryFn: () => clinicPageBuilderApi.list(),
  });
}

export function useUpdateClinicPageSection(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: ClinicPageSectionFormValues) =>
      clinicPageBuilderApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useReorderClinicPageSections() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: ReorderPayload) => clinicPageBuilderApi.reorder(payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
