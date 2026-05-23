import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { User, UserFormValues } from '../types';

export interface UserListParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  filter?: { is_active?: boolean };
}

function buildParams(p: UserListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.sort) params.sort = p.sort;
  if (p.filter?.is_active !== undefined) params['filter[is_active]'] = p.filter.is_active;
  return params;
}

export const usersApi = {
  list: async (params: UserListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<User>>('/admin/users', { params: buildParams(params) });
    return res.data;
  },
  get: async (id: number) => {
    const res = await apiClient.get<SingleResponse<User>>(`/admin/users/${id}`);
    return res.data.data;
  },
  create: async (values: UserFormValues) => {
    const res = await apiClient.post<SingleResponse<User>>('/admin/users', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<UserFormValues>) => {
    const res = await apiClient.patch<SingleResponse<User>>(`/admin/users/${id}`, values);
    return res.data.data;
  },
};
