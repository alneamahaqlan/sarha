import { apiClient } from '@/lib/api-client';

export type CartStatus = 'disabled' | 'pending' | 'active' | 'rejected';

export interface CartRequestClinic {
  id: number;
  name: string;
  slug: string;
  cart_status: CartStatus;
  cart_rejection_reason: string | null;
  cart_requested_at: string | null;
}

export const adminCartApi = {
  requests: async (status: string) =>
    (await apiClient.get<{ data: CartRequestClinic[] }>('/admin/cart/requests', { params: { status } })).data.data,

  approve: async (id: number) =>
    (await apiClient.post(`/admin/clinics/${id}/cart/approve`)).data,

  reject: async (id: number, reason: string) =>
    (await apiClient.post(`/admin/clinics/${id}/cart/reject`, { reason })).data,

  disable: async (id: number) =>
    (await apiClient.post(`/admin/clinics/${id}/cart/disable`)).data,
};
