import { apiClient } from '@/lib/api-client';

export type QuoteAccessStatus = 'active' | 'disabled' | 'pending' | 'rejected';

export interface QuoteAccessClinic {
  id: number;
  name: string;
  slug: string;
  price_quote_status: QuoteAccessStatus;
  price_quote_rejection_reason: string | null;
  price_quote_requested_at: string | null;
}

export const adminQuoteAccessApi = {
  requests: async (status: string, search: string) =>
    (await apiClient.get<{ data: QuoteAccessClinic[] }>('/admin/price-quote-access/requests', {
      params: { status, ...(search ? { search } : {}) },
    })).data.data,

  approve: async (id: number) =>
    (await apiClient.post(`/admin/clinics/${id}/price-quote-access/approve`)).data,

  reject: async (id: number, reason: string) =>
    (await apiClient.post(`/admin/clinics/${id}/price-quote-access/reject`, { reason })).data,

  disable: async (id: number) =>
    (await apiClient.post(`/admin/clinics/${id}/price-quote-access/disable`)).data,
};
