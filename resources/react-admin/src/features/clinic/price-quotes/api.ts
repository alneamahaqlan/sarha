import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { PriceQuote, PriceQuoteStatus } from '@/features/price-quotes/types';

export interface ClinicQuoteUpdateValues {
  status?: PriceQuoteStatus;
  clinic_reply?: string | null;
}

export interface ListParams {
  page?: number;
  per_page?: number;
  search?: string;
  filter?: { status?: PriceQuoteStatus };
}

function buildParams(p: ListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.filter?.status) params['filter[status]'] = p.filter.status;
  return params;
}

export const clinicQuotesApi = {
  list: async (params: ListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<PriceQuote>>('/clinic/price-quotes', { params: buildParams(params) });
    return res.data;
  },
  update: async (id: number, values: ClinicQuoteUpdateValues) => {
    const res = await apiClient.patch<SingleResponse<PriceQuote>>(`/clinic/price-quotes/${id}`, values);
    return res.data.data;
  },
};
