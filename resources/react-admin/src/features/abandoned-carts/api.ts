import { apiClient } from '@/lib/api-client';

export interface AbandonedCartClinicSummary {
  clinic: { id: number; name: string; slug: string } | null;
  carts_count: number;
  items_count: number;
  last_contacted_at: string | null;
  contacted_recently: boolean;
}

export interface AbandonedCartItem {
  id: number;
  type: 'service' | 'offer' | 'package' | 'item';
  name: string;
  price: number | null;
  deleted: boolean;
  added_at: string | null;
  booked_at: string | null;
}

export interface AbandonedCart {
  user: { id: number; name: string; phone: string } | null;
  items: AbandonedCartItem[];
  last_contacted_at: string | null;
  contacted_recently: boolean;
}

export interface AbandonedCartClinicDetail {
  clinic: { id: number; name: string; slug: string };
  carts: AbandonedCart[];
}

export const adminAbandonedCartsApi = {
  index: async () =>
    (await apiClient.get<{ data: AbandonedCartClinicSummary[] }>('/admin/abandoned-carts')).data.data,
  show: async (clinicId: number) =>
    (await apiClient.get<{ data: AbandonedCartClinicDetail }>(`/admin/abandoned-carts/${clinicId}`)).data.data,
};
