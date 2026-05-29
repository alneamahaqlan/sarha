import { apiClient } from '@/lib/api-client';
import type { SingleResponse } from '@/types/api';
import type { Offer, OfferFormValues, OfferStatus } from './types';

export const clinicOffersApi = {
  list: async (status?: OfferStatus) => {
    const res = await apiClient.get<{ data: Offer[] }>('/clinic/offers', {
      params: status ? { status } : undefined,
    });
    return res.data.data;
  },
  create: async (values: OfferFormValues) => {
    const res = await apiClient.post<SingleResponse<Offer>>('/clinic/offers', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<OfferFormValues>) => {
    const res = await apiClient.patch<SingleResponse<Offer>>(`/clinic/offers/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/clinic/offers/${id}`);
  },
  extend: async (id: number, days: number) => {
    const res = await apiClient.post<SingleResponse<Offer>>(`/clinic/offers/${id}/extend`, { days });
    return res.data.data;
  },
};
