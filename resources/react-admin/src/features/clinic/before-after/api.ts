import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';

export interface BeforeAfterPhoto {
  id: number;
  clinic_id: number;
  sub_clinic_id: number | null;
  service_id: number | null;
  title: string | null;
  before_image: string;
  after_image: string;
  display_mode: 'side_by_side' | 'slider';
  before_url: string | null;
  after_url: string | null;
  is_active: boolean;
  sort_order: number;
  sub_clinic?: { id: number; name: string } | null;
  service?: { id: number; name: string } | null;
}

export interface BeforeAfterFormValues {
  title?: string | null;
  before_image: string;
  after_image: string;
  display_mode?: 'side_by_side' | 'slider';
  sub_clinic_id?: number | null;
  service_id?: number | null;
  is_active: boolean;
  sort_order?: number;
}

export const clinicBeforeAfterApi = {
  list: async () => {
    const res = await apiClient.get<PaginatedResponse<BeforeAfterPhoto>>('/clinic/before-after');
    return res.data;
  },
  create: async (values: BeforeAfterFormValues) => {
    const res = await apiClient.post<SingleResponse<BeforeAfterPhoto>>('/clinic/before-after', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<BeforeAfterFormValues>) => {
    const res = await apiClient.patch<SingleResponse<BeforeAfterPhoto>>(`/clinic/before-after/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/clinic/before-after/${id}`);
  },
};
