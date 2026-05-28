import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicCategoryRequestsApi } from './api';

const KEY = ['clinic', 'category-requests'] as const;

export function useClinicCategoryRequests() {
  return useQuery({ queryKey: KEY, queryFn: () => clinicCategoryRequestsApi.list() });
}

export function useCreateClinicCategoryRequest() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: { name: string; service_id?: number | null }) =>
      clinicCategoryRequestsApi.create(input.name, input.service_id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
