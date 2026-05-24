import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicSubClinicsApi, type ClinicSubClinicFormValues } from './api';

const KEY = ['clinic', 'sub-clinics'] as const;

export function useClinicSubClinics(search?: string) {
  return useQuery({
    queryKey: [...KEY, 'list', search ?? null],
    queryFn: () => clinicSubClinicsApi.list(search),
  });
}

export function useCreateClinicSubClinic() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: ClinicSubClinicFormValues) => clinicSubClinicsApi.create(v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateClinicSubClinic(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: Partial<ClinicSubClinicFormValues>) => clinicSubClinicsApi.update(id, v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteClinicSubClinic() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => clinicSubClinicsApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
