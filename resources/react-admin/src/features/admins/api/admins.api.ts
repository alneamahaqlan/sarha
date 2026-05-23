import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { AdminFormValues, AdminUser } from '../types';

export interface AdminListParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  filter?: { is_active?: boolean; role?: string };
}

function buildParams(p: AdminListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.sort) params.sort = p.sort;
  if (p.filter?.is_active !== undefined) params['filter[is_active]'] = p.filter.is_active;
  if (p.filter?.role) params['filter[role]'] = p.filter.role;
  return params;
}

export const adminsApi = {
  list: async (params: AdminListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<AdminUser>>('/admin/admins', { params: buildParams(params) });
    return res.data;
  },
  get: async (id: number) => {
    const res = await apiClient.get<SingleResponse<AdminUser>>(`/admin/admins/${id}`);
    return res.data.data;
  },
  create: async (values: AdminFormValues) => {
    const res = await apiClient.post<SingleResponse<AdminUser>>('/admin/admins', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<AdminFormValues>) => {
    const res = await apiClient.patch<SingleResponse<AdminUser>>(`/admin/admins/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/admin/admins/${id}`);
  },
};
