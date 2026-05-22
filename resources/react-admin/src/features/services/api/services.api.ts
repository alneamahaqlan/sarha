import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { Service, ServiceFormValues } from '../types';

export interface ServiceListParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  filter?: { clinic_id?: number; is_active?: boolean };
}

function buildParams(p: ServiceListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.sort) params.sort = p.sort;
  if (p.filter?.clinic_id) params['filter[clinic_id]'] = p.filter.clinic_id;
  if (p.filter?.is_active !== undefined) params['filter[is_active]'] = p.filter.is_active;
  return params;
}

export const servicesApi = {
  list: async (params: ServiceListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<Service>>('/admin/services', { params: buildParams(params) });
    return res.data;
  },
  get: async (id: number) => {
    const res = await apiClient.get<SingleResponse<Service>>(`/admin/services/${id}`);
    return res.data.data;
  },
  create: async (values: ServiceFormValues) => {
    const res = await apiClient.post<SingleResponse<Service>>('/admin/services', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<ServiceFormValues>) => {
    const res = await apiClient.patch<SingleResponse<Service>>(`/admin/services/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/admin/services/${id}`);
  },
};
