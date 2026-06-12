import { apiClient } from '@/lib/api-client';

export interface ClinicCartItem {
  id: number;
  type: 'service' | 'offer' | 'package' | 'item';
  name: string;
  price: number | null;
  deleted: boolean;
  added_at: string | null;
  booked_at: string | null;
}

export interface ClinicAbandonedCart {
  user: { id: number; name: string; phone: string } | null;
  items: ClinicCartItem[];
  last_contacted_at: string | null;
  contacted_recently: boolean;
}

export interface ContactResult {
  whatsapp_link: string | null;
  tel_link: string | null;
  contacted_at: string;
}

export interface ConvertResult {
  booking_id: number;
  reference_code: string;
}

export const clinicAbandonedCartsApi = {
  index: async () =>
    (await apiClient.get<{ data: ClinicAbandonedCart[] }>('/clinic/abandoned-carts')).data.data,

  contact: async (userId: number, channel: 'whatsapp' | 'call' | 'manual') =>
    (await apiClient.post<{ data: ContactResult }>(`/clinic/abandoned-carts/${userId}/contact`, { channel })).data.data,

  convert: async (userId: number) =>
    (await apiClient.post<{ data: ConvertResult }>(`/clinic/abandoned-carts/${userId}/convert`)).data.data,
};
