import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';

export interface ClinicDoctor {
  id: number;
  clinic_id: number;
  name: string;
  specialty: string | null;
  photo: string | null;
  photo_url: string | null;
  bio: string | null;
  years_experience: number | null;
  is_active: boolean;
  sort_order: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface ClinicDoctorFormValues {
  name: string;
  specialty?: string | null;
  photo?: string | null;
  bio?: string | null;
  years_experience?: number | null;
  is_active: boolean;
  sort_order?: number;
}

export const clinicDoctorsApi = {
  list: async (search?: string) => {
    const res = await apiClient.get<PaginatedResponse<ClinicDoctor>>('/clinic/doctors', {
      params: search ? { search } : {},
    });
    return res.data;
  },
  create: async (values: ClinicDoctorFormValues) => {
    const res = await apiClient.post<SingleResponse<ClinicDoctor>>('/clinic/doctors', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<ClinicDoctorFormValues>) => {
    const res = await apiClient.patch<SingleResponse<ClinicDoctor>>(`/clinic/doctors/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/clinic/doctors/${id}`);
  },
};
