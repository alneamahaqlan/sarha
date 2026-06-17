import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { AdminReviewRow, ModFilters } from './types';

export const reviewModerationApi = {
  list: async (filters: ModFilters = {}): Promise<PaginatedResponse<AdminReviewRow>> => {
    const params: Record<string, string | number> = {};
    if (filters.scope) params.scope = filters.scope;
    if (filters.page) params.page = filters.page;
    if (filters.per_page) params.per_page = filters.per_page;
    return (await apiClient.get<PaginatedResponse<AdminReviewRow>>('/admin/review-moderation', { params })).data;
  },

  moderate: async (id: number, action: 'hide' | 'dismiss', reason?: string): Promise<AdminReviewRow> =>
    (await apiClient.post<SingleResponse<AdminReviewRow>>(`/admin/review-moderation/${id}/moderate`, { action, reason })).data.data,
};
