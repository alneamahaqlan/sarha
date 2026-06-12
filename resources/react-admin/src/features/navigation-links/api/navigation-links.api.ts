import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { NavLocation, NavigationLink, NavigationLinkFormValues } from '../types';

export interface NavigationLinkListParams {
  page?: number;
  per_page?: number;
  filter?: { location?: NavLocation; is_active?: boolean };
}

function buildParams(p: NavigationLinkListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.filter?.location) params['filter[location]'] = p.filter.location;
  if (p.filter?.is_active !== undefined) params['filter[is_active]'] = p.filter.is_active;
  return params;
}

export interface ReorderPayload {
  order: { id: number; sort_order: number }[];
}

export const navigationLinksApi = {
  list: async (params: NavigationLinkListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<NavigationLink>>('/admin/navigation-links', { params: buildParams(params) });
    return res.data;
  },
  get: async (id: number) => {
    const res = await apiClient.get<SingleResponse<NavigationLink>>(`/admin/navigation-links/${id}`);
    return res.data.data;
  },
  create: async (values: NavigationLinkFormValues) => {
    const res = await apiClient.post<SingleResponse<NavigationLink>>('/admin/navigation-links', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<NavigationLinkFormValues>) => {
    const res = await apiClient.patch<SingleResponse<NavigationLink>>(`/admin/navigation-links/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/admin/navigation-links/${id}`);
  },
  reorder: async (payload: ReorderPayload) => {
    await apiClient.post('/admin/navigation-links/reorder', payload);
  },
};
