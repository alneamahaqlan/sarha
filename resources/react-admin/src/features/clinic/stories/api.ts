import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';

export interface Story {
  id: number;
  clinic_id: number;
  image: string;
  image_url: string | null;
  caption: string | null;
  views_count: number;
  ends_at: string | null;
  is_active: boolean;
  sort_order: number;
}

export interface StoryFormValues {
  image: string;
  caption?: string | null;
  ends_at?: string | null;
  is_active: boolean;
  sort_order?: number;
}

export const clinicStoriesApi = {
  list: async () => {
    const res = await apiClient.get<PaginatedResponse<Story>>('/clinic/stories');
    return res.data;
  },
  create: async (values: StoryFormValues) => {
    const res = await apiClient.post<SingleResponse<Story>>('/clinic/stories', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<StoryFormValues>) => {
    const res = await apiClient.patch<SingleResponse<Story>>(`/clinic/stories/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/clinic/stories/${id}`);
  },
};
