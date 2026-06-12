import { apiClient } from '@/lib/api-client';
import type { CartStatus } from '@/features/cart/api';

export type { CartStatus };

export interface ClinicCartData {
  cart_status: CartStatus;
  cart_rejection_reason: string | null;
  cart_requested_at: string | null;
  cart_storefront_enabled: boolean;
}

export const clinicCartApi = {
  show: async () =>
    (await apiClient.get<{ data: ClinicCartData }>('/clinic/cart')).data.data,

  update: async (v: { cart_storefront_enabled: boolean }) =>
    (await apiClient.put<{ data: ClinicCartData }>('/clinic/cart', v)).data.data,

  request: async () =>
    (await apiClient.post<{ data: ClinicCartData }>('/clinic/cart/request')).data.data,
};
