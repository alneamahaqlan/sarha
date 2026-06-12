import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';

export type ClinicQuoteFilter = 'pending' | 'replied';

export interface QuoteReply {
  id: number;
  body: string;
  price: number | null;
  is_public: boolean;
}

export interface BroadcastQuote {
  id: number;
  customer_name: string;
  // customer_phone is intentionally not exposed: clinics reply in-platform
  // and never receive the customer's contact channel.
  service_name: string;
  description: string | null;
  status: string;
  created_at: string | null;
  cities: { id: number; name: string }[];
  categories: { id: number; name: string }[];
  replies_count: number;
  my_reply: QuoteReply | null;
}

export type QuoteAccessStatus = 'active' | 'disabled' | 'pending' | 'rejected';

export interface QuoteAccessState {
  price_quote_status: QuoteAccessStatus;
  price_quote_rejection_reason: string | null;
  price_quote_requested_at: string | null;
}

export interface QuoteReplyValues {
  body: string;
  price?: number | null;
  is_public?: boolean;
}

export interface ListParams {
  page?: number;
  per_page?: number;
  search?: string;
  filter?: { status?: ClinicQuoteFilter };
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
    const res = await apiClient.get<PaginatedResponse<BroadcastQuote>>('/clinic/price-quotes', { params: buildParams(params) });
    return res.data;
  },
  reply: async (id: number, values: QuoteReplyValues) => {
    const res = await apiClient.post<SingleResponse<BroadcastQuote>>(`/clinic/price-quotes/${id}/reply`, values);
    return res.data.data;
  },
  access: async () => {
    const res = await apiClient.get<SingleResponse<QuoteAccessState>>('/clinic/price-quotes/access');
    return res.data.data;
  },
  requestAccess: async () => {
    const res = await apiClient.post<SingleResponse<QuoteAccessState>>('/clinic/price-quotes/access/request');
    return res.data.data;
  },
};
