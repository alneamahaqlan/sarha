import { apiClient } from '@/lib/api-client';
import type { Campaign, CampaignAudience, CampaignRecipient, CreateCampaignInput, RecipientStatus } from './types';

export const campaignsApi = {
  list: async () => {
    const res = await apiClient.get<{ data: Campaign[] }>('/clinic/campaigns');
    return res.data.data;
  },

  preview: async (audience: CampaignAudience) => {
    const res = await apiClient.post<{ data: { count: number } }>('/clinic/campaigns/preview', { audience });
    return res.data.data.count;
  },

  create: async (payload: CreateCampaignInput) => {
    const res = await apiClient.post<{ data: Campaign }>('/clinic/campaigns', payload);
    return res.data.data;
  },

  get: async (id: number) => {
    const res = await apiClient.get<{ data: Campaign }>(`/clinic/campaigns/${id}`);
    return res.data.data;
  },

  recipients: async (id: number, status?: RecipientStatus) => {
    const res = await apiClient.get<{ data: CampaignRecipient[] }>(`/clinic/campaigns/${id}/recipients`, {
      params: status ? { status } : {},
    });
    return res.data.data;
  },

  mark: async (campaignId: number, recipientId: number, status: RecipientStatus) => {
    const res = await apiClient.post<{ data: { recipient: CampaignRecipient; campaign: Campaign } }>(
      `/clinic/campaigns/${campaignId}/recipients/${recipientId}/mark`,
      { status },
    );
    return res.data.data;
  },

  remove: async (id: number) => {
    await apiClient.delete(`/clinic/campaigns/${id}`);
  },
};
