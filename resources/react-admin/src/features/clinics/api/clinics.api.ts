import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { Clinic, ClinicPlan, ClinicStatus } from '../types';

export type TrashedFilter = 'with' | 'without' | 'only';

export interface ClinicListParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  filter?: {
    status?: ClinicStatus;
    subscription_type?: ClinicPlan;
    city_id?: number;
    trashed?: TrashedFilter;
  };
}

function buildParams(p: ClinicListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.sort) params.sort = p.sort;
  if (p.filter?.status) params['filter[status]'] = p.filter.status;
  if (p.filter?.subscription_type) params['filter[subscription_type]'] = p.filter.subscription_type;
  if (p.filter?.city_id) params['filter[city_id]'] = p.filter.city_id;
  if (p.filter?.trashed) params['filter[trashed]'] = p.filter.trashed;
  return params;
}

export const clinicsApi = {
  list: async (params: ClinicListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<Clinic>>('/admin/clinics', { params: buildParams(params) });
    return res.data;
  },
  get: async (id: number) => {
    const res = await apiClient.get<SingleResponse<Clinic>>(`/admin/clinics/${id}`);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/admin/clinics/${id}`);
  },

  // Actions
  approve: async (id: number) => {
    const res = await apiClient.post<SingleResponse<Clinic>>(`/admin/clinics/${id}/approve`);
    return res.data.data;
  },
  reject: async (id: number, reason: string) => {
    const res = await apiClient.post<SingleResponse<Clinic>>(`/admin/clinics/${id}/reject`, {
      rejection_reason: reason,
    });
    return res.data.data;
  },
  activate: async (id: number) => {
    const res = await apiClient.post<SingleResponse<Clinic>>(`/admin/clinics/${id}/activate`);
    return res.data.data;
  },
  suspend: async (id: number) => {
    const res = await apiClient.post<SingleResponse<Clinic>>(`/admin/clinics/${id}/suspend`);
    return res.data.data;
  },
  extend: async (id: number, days: 30 | 90) => {
    const res = await apiClient.post<SingleResponse<Clinic>>(`/admin/clinics/${id}/extend`, { days });
    return res.data.data;
  },
  impersonate: async (id: number) => {
    const res = await apiClient.post<{ data: { redirect: string; clinic: Clinic } }>(
      `/admin/clinics/${id}/impersonate`,
    );
    return res.data.data;
  },
  restore: async (id: number) => {
    const res = await apiClient.post<SingleResponse<Clinic>>(`/admin/clinics/${id}/restore`);
    return res.data.data;
  },
  bulk: async (action: 'delete' | 'restore' | 'force_delete', ids: number[]) => {
    const res = await apiClient.post<{ data: { affected: number } }>(`/admin/clinics/bulk`, {
      action,
      ids,
    });
    return res.data.data;
  },
};
