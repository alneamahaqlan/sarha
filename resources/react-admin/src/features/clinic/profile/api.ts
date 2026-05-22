import { apiClient } from '@/lib/api-client';
import type { SingleResponse } from '@/types/api';
import type { Clinic } from '@/features/clinics/types';

export interface ProfileFormValues {
  name?: string;
  phone?: string;
  email?: string | null;
  address?: string | null;
  description?: string | null;
  website?: string | null;
  instagram?: string | null;
  twitter?: string | null;
  snapchat?: string | null;
  logo?: string | null;
  password?: string;
}

export const clinicProfileApi = {
  show: async () => {
    const res = await apiClient.get<SingleResponse<Clinic>>('/clinic/profile');
    return res.data.data;
  },
  update: async (values: ProfileFormValues) => {
    const res = await apiClient.patch<SingleResponse<Clinic>>('/clinic/profile', values);
    return res.data.data;
  },
};
