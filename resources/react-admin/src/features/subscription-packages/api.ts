import { apiClient } from '@/lib/api-client';
import type { SubscriptionPackage, SubscriptionPackageFormValues } from './types';

const BASE = '/admin/subscription-packages';

export const subscriptionPackagesApi = {
  list: async (): Promise<SubscriptionPackage[]> => {
    const res = await apiClient.get<{ data: SubscriptionPackage[] }>(BASE);
    return res.data.data;
  },
  show: async (id: number): Promise<SubscriptionPackage> => {
    const res = await apiClient.get<{ data: SubscriptionPackage }>(`${BASE}/${id}`);
    return res.data.data;
  },
  create: async (values: SubscriptionPackageFormValues): Promise<SubscriptionPackage> => {
    const res = await apiClient.post<{ data: SubscriptionPackage }>(BASE, values);
    return res.data.data;
  },
  update: async (id: number, values: SubscriptionPackageFormValues): Promise<SubscriptionPackage> => {
    const res = await apiClient.put<{ data: SubscriptionPackage }>(`${BASE}/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`${BASE}/${id}`);
  },
};
