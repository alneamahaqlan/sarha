import { apiClient } from '@/lib/api-client';

export interface CatalogServiceRow {
  id: number;
  name: string;
  name_en: string | null;
  aliases: string[];
  status: 'pending' | 'active' | 'rejected';
  category: { id: number; name: string } | null;
  requested_by: { id: number; name: string } | null;
  services_count: number;
  created_at: string | null;
}

export interface CatalogServicesResponse {
  data: CatalogServiceRow[];
  meta: {
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
}

export const catalogServicesApi = {
  list: async (params: { status?: string; page?: number; search?: string } = {}) => {
    const res = await apiClient.get<CatalogServicesResponse>('/admin/catalog-services', {
      params: {
        ...(params.status ? { 'filter[status]': params.status } : {}),
        ...(params.page ? { page: params.page } : {}),
        ...(params.search ? { search: params.search } : {}),
      },
    });
    return res.data;
  },
  update: async (
    id: number,
    payload: { name?: string; name_en?: string | null; aliases?: string[] },
  ) => {
    const res = await apiClient.patch(`/admin/catalog-services/${id}`, payload);
    return res.data;
  },
  approve: async (id: number) => {
    const res = await apiClient.post(`/admin/catalog-services/${id}/approve`);
    return res.data;
  },
  reject: async (id: number) => {
    const res = await apiClient.post(`/admin/catalog-services/${id}/reject`);
    return res.data;
  },
};
