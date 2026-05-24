import { apiClient } from '@/lib/api-client';
import type { SingleResponse } from '@/types/api';
import type { Clinic } from '@/features/clinics/types';

export interface ProfileFormValues {
  name?: string;
  phone?: string;
  email?: string | null;
  address?: string | null;
  district?: string | null;
  description?: string | null;
  website?: string | null;
  instagram?: string | null;
  twitter?: string | null;
  snapchat?: string | null;
  logo?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  google_place_id?: string | null;
  password?: string;
}

export interface WorkingHourRow {
  day_of_week: number;
  is_open: boolean;
  open_time: string | null;
  close_time: string | null;
}

export interface ClinicReview {
  id: number;
  reviewer_name: string | null;
  reviewer_photo: string | null;
  rating: number;
  review_text: string | null;
  reviewed_at: string | null;
}

export interface ClinicReviews {
  average: number | null;
  count: number;
  reviews: ClinicReview[];
}

export interface ClinicSubscription {
  subscription_type: string | null;
  subscription_starts_at: string | null;
  subscription_ends_at: string | null;
  days_remaining: number;
  is_active: boolean;
  plans: { basic: number; premium: number };
  contact: { phone: string | null; email: string | null; whatsapp: string | null };
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
  extractCoords: async (url: string) => {
    const res = await apiClient.post<SingleResponse<{ latitude: number; longitude: number }>>(
      '/clinic/profile/extract-coords',
      { url },
    );
    return res.data.data;
  },
  syncReviews: async () => {
    const res = await apiClient.post<SingleResponse<{ fetched: number }>>('/clinic/profile/sync-reviews');
    return res.data.data;
  },
  reviews: async () => {
    const res = await apiClient.get<SingleResponse<ClinicReviews>>('/clinic/reviews');
    return res.data.data;
  },
  workingHours: async () => {
    const res = await apiClient.get<{ data: WorkingHourRow[] }>('/clinic/working-hours');
    return res.data.data;
  },
  updateWorkingHours: async (hours: WorkingHourRow[]) => {
    const res = await apiClient.put<{ data: WorkingHourRow[] }>('/clinic/working-hours', { hours });
    return res.data.data;
  },
  subscription: async () => {
    const res = await apiClient.get<SingleResponse<ClinicSubscription>>('/clinic/subscription');
    return res.data.data;
  },
};
