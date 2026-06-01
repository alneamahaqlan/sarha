import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { Service } from '@/features/services/types';

export interface ClinicServiceFormValues {
  name: string;
  category_ids: number[];
  sub_clinic_id?: number | null;
  description?: string | null;
  price: number;
  image?: string | null;
  is_active: boolean;
  sort_order?: number;
}

export interface ListParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  filter?: { is_active?: boolean };
}

function buildParams(p: ListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.sort) params.sort = p.sort;
  if (p.filter?.is_active !== undefined) params['filter[is_active]'] = p.filter.is_active;
  return params;
}

export const clinicServicesApi = {
  list: async (params: ListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<Service>>('/clinic/services', { params: buildParams(params) });
    return res.data;
  },
  create: async (values: ClinicServiceFormValues) => {
    const res = await apiClient.post<SingleResponse<Service>>('/clinic/services', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<ClinicServiceFormValues>) => {
    const res = await apiClient.patch<SingleResponse<Service>>(`/clinic/services/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/clinic/services/${id}`);
  },
  reorder: async (order: { id: number; sort_order: number }[]) => {
    await apiClient.post('/clinic/services/reorder', { order });
  },
};
