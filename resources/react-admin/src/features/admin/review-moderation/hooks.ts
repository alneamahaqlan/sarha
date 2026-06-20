import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { reviewModerationApi } from './api';
import type { ModFilters } from './types';

const KEY = ['admin', 'review-moderation'] as const;

export function useReportedReviews(filters: ModFilters = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', filters],
    queryFn: () => reviewModerationApi.list(filters),
  });
}

export function useModerateReview() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: { id: number; action: 'hide' | 'dismiss'; reason?: string }) =>
      reviewModerationApi.moderate(input.id, input.action, input.reason),
    onSuccess: () => qc.invalidateQueries({ queryKey: [...KEY, 'list'] }),
  });
}
