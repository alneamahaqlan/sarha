import { apiClient } from '@/lib/api-client';

export interface AiChatClinic {
  id: number;
  name: string;
  slug?: string;
  city?: { id: number; name: string } | null;
  [key: string]: unknown;
}

export interface AiChatResponse {
  reply: string;
  clinics: AiChatClinic[];
  kind: 'matched' | 'rejected' | 'no_match' | 'empty';
}

export const aiChatApi = {
  ask: async (query: string, city_id?: number | null) => {
    const res = await apiClient.post<{ data: AiChatResponse }>('/ai-chat', {
      query,
      ...(city_id ? { city_id } : {}),
    });
    return res.data.data;
  },
};
