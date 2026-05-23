import { apiClient } from '@/lib/api-client';

export type MassNotifyAudience = 'all' | 'premium' | 'basic' | 'expiring';
export type MassNotifyPriority = 'low' | 'normal' | 'high' | 'urgent';
export type MassNotifyChannel = 'in_app' | 'email' | 'both';

export interface MassNotifyPayload {
  audience: MassNotifyAudience;
  priority: MassNotifyPriority;
  channel: MassNotifyChannel;
  title: string;
  body: string;
}

interface MassNotifyResponse {
  data: { sent: number };
  message: string;
}

export const massNotifyApi = {
  send: async (payload: MassNotifyPayload) => {
    const res = await apiClient.post<MassNotifyResponse>('/admin/mass-notify', payload);
    return res.data;
  },
};
