import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';

export interface ClinicCustomCategory {
  id: number;
  clinic_id: number;
  name: string;
  emoji: string | null;
  is_active: boolean;
  sort_order: number;
  services_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface ClinicCategoryFormValues {
  name: string;
  emoji?: string | null;
  is_active: boolean;
  sort_order?: number;
}

export const clinicCategoriesApi = {
  list: async (search?: string) => {
    const res = await apiClient.get<PaginatedResponse<ClinicCustomCategory>>('/clinic/custom-categories', {
      params: search ? { search } : {},
    });
    return res.data;
  },
  create: async (values: ClinicCategoryFormValues) => {
    const res = await apiClient.post<SingleResponse<ClinicCustomCategory>>('/clinic/custom-categories', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<ClinicCategoryFormValues>) => {
    const res = await apiClient.patch<SingleResponse<ClinicCustomCategory>>(`/clinic/custom-categories/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/clinic/custom-categories/${id}`);
  },
  reorder: async (order: { id: number; sort_order: number }[]) => {
    await apiClient.post('/clinic/custom-categories/reorder', { order });
  },
};
