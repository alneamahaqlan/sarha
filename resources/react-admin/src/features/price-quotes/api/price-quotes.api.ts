import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { PriceQuote, PriceQuoteFormValues, PriceQuoteStatus } from '../types';

export interface PriceQuoteListParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  filter?: { status?: PriceQuoteStatus; clinic_id?: number };
}

function buildParams(p: PriceQuoteListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.sort) params.sort = p.sort;
  if (p.filter?.status) params['filter[status]'] = p.filter.status;
  if (p.filter?.clinic_id) params['filter[clinic_id]'] = p.filter.clinic_id;
  return params;
}

export const priceQuotesApi = {
  list: async (params: PriceQuoteListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<PriceQuote>>('/admin/price-quotes', { params: buildParams(params) });
    return res.data;
  },
  update: async (id: number, values: Partial<PriceQuoteFormValues>) => {
    const res = await apiClient.patch<SingleResponse<PriceQuote>>(`/admin/price-quotes/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/admin/price-quotes/${id}`);
  },
};
