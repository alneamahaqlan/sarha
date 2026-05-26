import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';

export interface ClinicPackage {
  id: number;
  clinic_id: number;
  name: string;
  description: string | null;
  price: number;
  old_price: number | null;
  discount_percentage: number | null;
  expires_at: string | null;
  is_active: boolean;
  sort_order: number;
  service_ids?: number[];
  services?: { id: number; name: string }[];
  created_at: string | null;
  updated_at: string | null;
}

export interface ClinicPackageFormValues {
  name: string;
  description?: string | null;
  price: number;
  old_price?: number | null;
  expires_at?: string | null;
  is_active: boolean;
  sort_order?: number;
  service_ids: number[];
}

export const clinicPackagesApi = {
  list: async (search?: string) => {
    const res = await apiClient.get<PaginatedResponse<ClinicPackage>>('/clinic/packages', {
      params: search ? { search } : {},
    });
    return res.data;
  },
  create: async (values: ClinicPackageFormValues) => {
    const res = await apiClient.post<SingleResponse<ClinicPackage>>('/clinic/packages', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<ClinicPackageFormValues>) => {
    const res = await apiClient.patch<SingleResponse<ClinicPackage>>(`/clinic/packages/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/clinic/packages/${id}`);
  },
};
