import { apiClient } from '@/lib/api-client';

export interface ClinicCategoryRequest {
  id: number;
  name: string;
  status: 'pending' | 'approved' | 'rejected';
  created_at: string | null;
}

export const clinicCategoryRequestsApi = {
  list: async () => {
    const res = await apiClient.get<{ data: ClinicCategoryRequest[] }>('/clinic/category-requests');
    return res.data.data;
  },
  create: async (name: string) => {
    const res = await apiClient.post<{ data: ClinicCategoryRequest; message: string }>('/clinic/category-requests', { name });
    return res.data;
  },
};
