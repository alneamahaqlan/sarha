import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';

export interface ClinicSubClinic {
  id: number;
  clinic_id: number;
  category_id: number | null;
  name: string;
  name_en: string | null;
  description: string | null;
  is_active: boolean;
  sort_order: number;
  services_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface ClinicSubClinicFormValues {
  name: string;
  name_en?: string | null;
  category_id?: number | null;
  description?: string | null;
  is_active: boolean;
  sort_order?: number;
}

export const clinicSubClinicsApi = {
  list: async (search?: string) => {
    const res = await apiClient.get<PaginatedResponse<ClinicSubClinic>>('/clinic/sub-clinics', {
      params: search ? { search } : {},
    });
    return res.data;
  },
  create: async (values: ClinicSubClinicFormValues) => {
    const res = await apiClient.post<SingleResponse<ClinicSubClinic>>('/clinic/sub-clinics', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<ClinicSubClinicFormValues>) => {
    const res = await apiClient.patch<SingleResponse<ClinicSubClinic>>(`/clinic/sub-clinics/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/clinic/sub-clinics/${id}`);
  },
  reorder: async (order: { id: number; sort_order: number }[]) => {
    await apiClient.post('/clinic/sub-clinics/reorder', { order });
  },
};
