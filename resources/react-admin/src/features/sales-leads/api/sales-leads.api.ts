import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { SalesLead, SalesLeadFormValues, SalesLeadStatus, SubscriptionPlan } from '../types';

export interface SalesLeadListParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  filter?: { status?: SalesLeadStatus };
}

function buildParams(p: SalesLeadListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.sort) params.sort = p.sort;
  if (p.filter?.status) params['filter[status]'] = p.filter.status;
  return params;
}

interface ConvertResponse {
  data: {
    lead: SalesLead;
    clinic: { id: number; name: string; subscription_type: string };
  };
}

export const salesLeadsApi = {
  list: async (params: SalesLeadListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<SalesLead>>('/admin/sales-leads', { params: buildParams(params) });
    return res.data;
  },
  get: async (id: number) => {
    const res = await apiClient.get<SingleResponse<SalesLead>>(`/admin/sales-leads/${id}`);
    return res.data.data;
  },
  create: async (values: SalesLeadFormValues) => {
    const res = await apiClient.post<SingleResponse<SalesLead>>('/admin/sales-leads', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<SalesLeadFormValues>) => {
    const res = await apiClient.patch<SingleResponse<SalesLead>>(`/admin/sales-leads/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/admin/sales-leads/${id}`);
  },
  convert: async (id: number, plan: SubscriptionPlan) => {
    const res = await apiClient.post<ConvertResponse>(`/admin/sales-leads/${id}/convert`, { plan });
    return res.data.data;
  },
};
