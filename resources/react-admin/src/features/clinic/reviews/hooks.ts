import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicReviewsApi } from './api';
import type { ReviewFilters } from './types';

const KEY = ['clinic', 'verified-reviews'] as const;

export function useClinicReviews(filters: ReviewFilters = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', filters],
    queryFn: () => clinicReviewsApi.list(filters),
  });
}

export function useReplyReview() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: { id: number; text: string }) => clinicReviewsApi.reply(input.id, input.text),
    onSuccess: () => qc.invalidateQueries({ queryKey: [...KEY, 'list'] }),
  });
}

export function useReportReview() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: { id: number; reason: string; note?: string }) => clinicReviewsApi.report(input.id, input.reason, input.note),
    onSuccess: () => qc.invalidateQueries({ queryKey: [...KEY, 'list'] }),
  });
}
