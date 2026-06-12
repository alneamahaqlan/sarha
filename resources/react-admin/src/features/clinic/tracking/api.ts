import { apiClient } from '@/lib/api-client';
import type { Pixel, ProviderMeta, TrackingStatus } from '@/features/tracking/api';

export type { Pixel, ProviderMeta, TrackingStatus };

export interface ClinicTrackingData {
  tracking_status: TrackingStatus;
  tracking_pixels: Pixel[];
  advanced_matching_optin: boolean;
  tracking_rejection_reason: string | null;
  tracking_requested_at: string | null;
  feature_enabled: boolean;
  providers: ProviderMeta[];
}

export const clinicTrackingApi = {
  show: async () =>
    (await apiClient.get<{ data: ClinicTrackingData }>('/clinic/tracking')).data.data,

  update: async (v: { tracking_pixels: Pixel[]; advanced_matching_optin: boolean }) =>
    (await apiClient.put<{ data: ClinicTrackingData }>('/clinic/tracking', v)).data.data,

  request: async () =>
    (await apiClient.post<{ data: ClinicTrackingData }>('/clinic/tracking/request')).data.data,
};
