import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { City, CityFormValues } from '../types';

export interface CityListParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  filter?: { is_active?: boolean };
}

function buildParams(p: CityListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.sort) params.sort = p.sort;
  if (p.filter?.is_active !== undefined) params['filter[is_active]'] = p.filter.is_active;
  return params;
}

export const citiesApi = {
  list: async (params: CityListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<City>>('/admin/cities', { params: buildParams(params) });
    return res.data;
  },
  get: async (id: number) => {
    const res = await apiClient.get<SingleResponse<City>>(`/admin/cities/${id}`);
    return res.data.data;
  },
  create: async (values: CityFormValues) => {
    const res = await apiClient.post<SingleResponse<City>>('/admin/cities', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<CityFormValues>) => {
    const res = await apiClient.patch<SingleResponse<City>>(`/admin/cities/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/admin/cities/${id}`);
  },
};
