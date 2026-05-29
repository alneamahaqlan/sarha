import { apiClient } from '@/lib/api-client';
import type {
  AiAnalyticsPayload,
  AiConversationsPage,
  AiConversationTurn,
  AiDashboardWidget,
  UserAiInterestsPayload,
} from './types';

export interface ConversationsListParams {
  page?: number;
  per_page?: number;
  status?: 'normal' | 'blocked' | 'emergency';
  from?: string;
  to?: string;
  user_id?: number;
  clinic_id?: number;
  category_id?: number;
  min_turns?: number;
  reveal?: boolean;
}

export const aiCenterPhase2Api = {
  analytics: async (days: 7 | 30 | 90) => {
    const res = await apiClient.get<{ data: AiAnalyticsPayload }>('/admin/ai-center/analytics', { params: { days } });
    return res.data.data;
  },
  conversations: async (params: ConversationsListParams) => {
    const res = await apiClient.get<AiConversationsPage>('/admin/ai-center/conversations', { params });
    return res.data;
  },
  conversation: async (id: string, reveal: boolean) => {
    const res = await apiClient.get<{ data: AiConversationTurn[]; meta: unknown }>(
      `/admin/ai-center/conversations/${id}`,
      { params: { reveal } },
    );
    return res.data.data;
  },
  dashboardWidget: async () => {
    const res = await apiClient.get<{ data: AiDashboardWidget }>('/admin/ai-center/dashboard-widget');
    return res.data.data;
  },
  userInterests: async (userId: number) => {
    const res = await apiClient.get<{ data: UserAiInterestsPayload }>(`/admin/users/${userId}/ai-interests`);
    return res.data.data;
  },
};
