import { apiClient } from '@/lib/api-client';

export type TrackingStatus = 'disabled' | 'pending' | 'active' | 'rejected';

export interface Pixel {
  provider: string;
  id: string;
  enabled?: boolean;
}

export interface ProviderMeta {
  key: string;
  label: string;
  example: string;
  pattern: string;
}

export interface TrackingSettings {
  feature_enabled: boolean;
  consent_enabled: boolean;
  consent_text_ar: string;
  consent_text_en: string;
  privacy_url: string;
  platform_pixels: Pixel[];
  providers: ProviderMeta[];
}

export interface TrackingSettingsUpdate {
  feature_enabled: boolean;
  consent_enabled: boolean;
  consent_text_ar: string;
  consent_text_en: string;
  privacy_url: string;
  platform_pixels: Pixel[];
}

export interface TrackingRequestClinic {
  id: number;
  name: string;
  slug: string;
  tracking_status: TrackingStatus;
  tracking_pixels: Pixel[] | null;
  advanced_matching_optin: boolean;
  tracking_requested_at: string | null;
}

export const adminTrackingApi = {
  settings: async () =>
    (await apiClient.get<{ data: TrackingSettings }>('/admin/tracking/settings')).data.data,

  updateSettings: async (v: TrackingSettingsUpdate) =>
    (await apiClient.put<{ data: TrackingSettings }>('/admin/tracking/settings', v)).data.data,

  requests: async (status: string) =>
    (await apiClient.get<{ data: TrackingRequestClinic[] }>('/admin/tracking/requests', { params: { status } })).data.data,

  approve: async (id: number) =>
    (await apiClient.post(`/admin/clinics/${id}/tracking/approve`)).data,

  reject: async (id: number, reason: string) =>
    (await apiClient.post(`/admin/clinics/${id}/tracking/reject`, { reason })).data,

  disable: async (id: number) =>
    (await apiClient.post(`/admin/clinics/${id}/tracking/disable`)).data,
};
