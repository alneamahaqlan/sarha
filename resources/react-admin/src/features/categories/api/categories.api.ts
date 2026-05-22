import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { Category, CategoryFormValues } from '../types';

export interface CategoryListParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  filter?: { is_active?: boolean };
}

function buildParams(p: CategoryListParams) {
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

export const categoriesApi = {
  list: async (params: CategoryListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<Category>>('/admin/categories', { params: buildParams(params) });
    return res.data;
  },
  get: async (id: number) => {
    const res = await apiClient.get<SingleResponse<Category>>(`/admin/categories/${id}`);
    return res.data.data;
  },
  create: async (values: CategoryFormValues) => {
    const res = await apiClient.post<SingleResponse<Category>>('/admin/categories', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<CategoryFormValues>) => {
    const res = await apiClient.patch<SingleResponse<Category>>(`/admin/categories/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/admin/categories/${id}`);
  },
  reorder: async (payload: ReorderPayload) => {
    await apiClient.post('/admin/categories/reorder', payload);
  },
};
