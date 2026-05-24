import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicDoctorsApi, type ClinicDoctorFormValues } from './api';

const KEY = ['clinic', 'doctors'] as const;

export function useClinicDoctors(search?: string) {
  return useQuery({
    queryKey: [...KEY, 'list', search ?? null],
    queryFn: () => clinicDoctorsApi.list(search),
  });
}

export function useCreateClinicDoctor() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: ClinicDoctorFormValues) => clinicDoctorsApi.create(v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateClinicDoctor(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: Partial<ClinicDoctorFormValues>) => clinicDoctorsApi.update(id, v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteClinicDoctor() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => clinicDoctorsApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
