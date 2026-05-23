import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { Subscription, SubscriptionFormValues, SubscriptionStatus, SubscriptionType } from '../types';

export interface SubscriptionListParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  filter?: { type?: SubscriptionType; status?: SubscriptionStatus; clinic_id?: number; expiring?: boolean };
}

function buildParams(p: SubscriptionListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.sort) params.sort = p.sort;
  if (p.filter?.type) params['filter[type]'] = p.filter.type;
  if (p.filter?.status) params['filter[status]'] = p.filter.status;
  if (p.filter?.clinic_id) params['filter[clinic_id]'] = p.filter.clinic_id;
  if (p.filter?.expiring) params['filter[expiring]'] = true;
  return params;
}

export const subscriptionsApi = {
  list: async (params: SubscriptionListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<Subscription>>('/admin/subscriptions', { params: buildParams(params) });
    return res.data;
  },
  create: async (values: SubscriptionFormValues) => {
    const res = await apiClient.post<SingleResponse<Subscription>>('/admin/subscriptions', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<SubscriptionFormValues>) => {
    const res = await apiClient.patch<SingleResponse<Subscription>>(`/admin/subscriptions/${id}`, values);
    return res.data.data;
  },
};
