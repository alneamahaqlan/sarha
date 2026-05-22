import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { adminsApi, type AdminListParams } from './api/admins.api';
import type { AdminFormValues } from './types';

const KEY = ['admin', 'admins'] as const;

export function useAdmins(params: AdminListParams = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', params],
    queryFn: () => adminsApi.list(params),
  });
}

export function useCreateAdmin() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: AdminFormValues) => adminsApi.create(values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateAdmin(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<AdminFormValues>) => adminsApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteAdmin() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => adminsApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
