import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { ReviewFilters, VerifiedReviewRow } from './types';

export const clinicReviewsApi = {
  list: async (filters: ReviewFilters = {}): Promise<PaginatedResponse<VerifiedReviewRow>> => {
    const params: Record<string, string | number> = {};
    if (filters.rating) params.rating = filters.rating;
    if (filters.replied) params.replied = filters.replied;
    if (filters.page) params.page = filters.page;
    if (filters.per_page) params.per_page = filters.per_page;
    return (await apiClient.get<PaginatedResponse<VerifiedReviewRow>>('/clinic/verified-reviews', { params })).data;
  },

  reply: async (id: number, text: string): Promise<VerifiedReviewRow> =>
    (await apiClient.post<SingleResponse<VerifiedReviewRow>>(`/clinic/verified-reviews/${id}/reply`, { reply: text })).data.data,

  report: async (id: number, reason: string, note?: string): Promise<VerifiedReviewRow> =>
    (await apiClient.post<SingleResponse<VerifiedReviewRow>>(`/clinic/verified-reviews/${id}/report`, { reason, note })).data.data,
};
