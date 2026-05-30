import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { subscriptionPackagesApi } from './api';
import type { SubscriptionPackageFormValues } from './types';

const KEY = ['subscription-packages'] as const;

export function usePackages() {
  return useQuery({ queryKey: KEY, queryFn: subscriptionPackagesApi.list });
}

export function useCreatePackage() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: SubscriptionPackageFormValues) => subscriptionPackagesApi.create(values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdatePackage(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: SubscriptionPackageFormValues) => subscriptionPackagesApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeletePackage() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => subscriptionPackagesApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
