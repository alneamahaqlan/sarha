import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { categoryRequestsApi, type CategoryRequestListParams } from './api';

const KEY = ['admin', 'category-requests'] as const;

export function useCategoryRequests(params: CategoryRequestListParams = {}) {
  return useQuery({
    queryKey: [...KEY, params],
    queryFn: () => categoryRequestsApi.list(params),
  });
}

export function useApproveCategoryRequest() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => categoryRequestsApi.approve(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useRejectCategoryRequest() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => categoryRequestsApi.reject(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
