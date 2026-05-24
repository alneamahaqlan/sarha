import { apiClient } from '@/lib/api-client';

export interface CategoryRequest {
  id: number;
  name: string;
  status: 'pending' | 'approved' | 'rejected';
  clinic: { id: number; name: string } | null;
  created_at: string | null;
}

export interface CategoryRequestListParams {
  page?: number;
  filter?: { status?: string };
}

export const categoryRequestsApi = {
  list: async (params: CategoryRequestListParams = {}) => {
    const query: Record<string, string | number> = {};
    if (params.page) query.page = params.page;
    if (params.filter?.status) query['filter[status]'] = params.filter.status;
    const res = await apiClient.get<{ data: CategoryRequest[]; meta: { current_page: number; last_page: number; total: number; from: number; to: number } }>(
      '/admin/category-requests', { params: query },
    );
    return res.data;
  },
  approve: async (id: number) => {
    const res = await apiClient.post<{ message: string }>(`/admin/category-requests/${id}/approve`);
    return res.data;
  },
  reject: async (id: number) => {
    const res = await apiClient.post<{ message: string }>(`/admin/category-requests/${id}/reject`);
    return res.data;
  },
};
