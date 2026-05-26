import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';

export interface ClinicDoctor {
  id: number;
  clinic_id: number;
  sub_clinic_id: number | null;
  sub_clinic?: { id: number; name: string } | null;
  name: string;
  specialty: string | null;
  gender: 'male' | 'female' | null;
  photo: string | null;
  photo_url: string | null;
  bio: string | null;
  qualifications: string | null;
  years_experience: number | null;
  university: string | null;
  languages: string | null;
  is_active: boolean;
  sort_order: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface ClinicDoctorFormValues {
  name: string;
  specialty?: string | null;
  gender?: 'male' | 'female' | null;
  sub_clinic_id?: number | null;
  photo?: string | null;
  bio?: string | null;
  qualifications?: string | null;
  years_experience?: number | null;
  university?: string | null;
  languages?: string | null;
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
