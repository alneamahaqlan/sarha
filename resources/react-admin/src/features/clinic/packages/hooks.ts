import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicPackagesApi, type ClinicPackageFormValues } from './api';

const KEY = ['clinic', 'packages'] as const;

export function useClinicPackages(search?: string) {
  return useQuery({
    queryKey: [...KEY, 'list', search ?? null],
    queryFn: () => clinicPackagesApi.list(search),
  });
}

export function useCreateClinicPackage() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: ClinicPackageFormValues) => clinicPackagesApi.create(v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateClinicPackage(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: Partial<ClinicPackageFormValues>) => clinicPackagesApi.update(id, v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteClinicPackage() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => clinicPackagesApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
