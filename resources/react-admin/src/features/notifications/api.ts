import { apiClient } from '@/lib/api-client';

export interface PlatformNotification {
  id: number;
  type: string;
  icon: string | null;
  url: string | null;
  priority: 'low' | 'normal' | 'high' | 'urgent';
  title: string;
  body: string;
  data: Record<string, unknown> | null;
  is_read: boolean;
  read_at: string | null;
  created_at: string | null;
}

interface ListResponse {
  data: PlatformNotification[];
  meta: { unread_count: number };
}

export const notificationsApi = {
  list: async (unreadOnly = false, limit = 20) => {
    const res = await apiClient.get<ListResponse>('/notifications', {
      params: { unread_only: unreadOnly ? 1 : 0, limit },
    });
    return res.data;
  },
  markRead: async (id: number) => {
    await apiClient.post(`/notifications/${id}/read`);
  },
  markAllRead: async () => {
    await apiClient.post('/notifications/read-all');
  },
};
