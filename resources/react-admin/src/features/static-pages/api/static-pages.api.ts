import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { StaticPage, StaticPageFormValues } from '../types';

export interface StaticPageListParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  filter?: { is_active?: boolean };
}

function buildParams(p: StaticPageListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.sort) params.sort = p.sort;
  if (p.filter?.is_active !== undefined) params['filter[is_active]'] = p.filter.is_active;
  return params;
}

export interface ReorderPayload {
  order: { id: number; sort_order: number }[];
}

export const staticPagesApi = {
  list: async (params: StaticPageListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<StaticPage>>('/admin/static-pages', { params: buildParams(params) });
    return res.data;
  },
  get: async (id: number) => {
    const res = await apiClient.get<SingleResponse<StaticPage>>(`/admin/static-pages/${id}`);
    return res.data.data;
  },
  create: async (values: StaticPageFormValues) => {
    const res = await apiClient.post<SingleResponse<StaticPage>>('/admin/static-pages', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<StaticPageFormValues>) => {
    const res = await apiClient.patch<SingleResponse<StaticPage>>(`/admin/static-pages/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/admin/static-pages/${id}`);
  },
  reorder: async (payload: ReorderPayload) => {
    await apiClient.post('/admin/static-pages/reorder', payload);
  },
};
