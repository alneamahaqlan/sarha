import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { usersApi, type UserListParams } from './api/users.api';
import type { UserFormValues } from './types';

const KEY = ['admin', 'users'] as const;

export function useUsers(params: UserListParams = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', params],
    queryFn: () => usersApi.list(params),
  });
}

export function useUser(id: number | null) {
  return useQuery({
    queryKey: [...KEY, 'detail', id],
    queryFn: () => usersApi.get(id as number),
    enabled: id !== null,
  });
}

export function useCreateUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: UserFormValues) => usersApi.create(values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateUser(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<UserFormValues>) => usersApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
