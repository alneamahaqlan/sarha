import { apiClient } from '@/lib/api-client';
import type { SingleResponse } from '@/types/api';
import type {
  HomepageSection,
  HomepageSectionFormValues,
  HomepageBannerSlide,
  BannerSlideFormValues,
} from '../types';

export interface ReorderPayload {
  order: { id: number; sort_order: number }[];
}

export const homepageSectionsApi = {
  list: async () => {
    const res = await apiClient.get<{ data: HomepageSection[] }>('/admin/homepage-sections');
    return res.data.data;
  },
  get: async (id: number) => {
    const res = await apiClient.get<SingleResponse<HomepageSection>>(`/admin/homepage-sections/${id}`);
    return res.data.data;
  },
  update: async (id: number, values: HomepageSectionFormValues) => {
    const res = await apiClient.patch<SingleResponse<HomepageSection>>(`/admin/homepage-sections/${id}`, values);
    return res.data.data;
  },
  reorder: async (payload: ReorderPayload) => {
    await apiClient.post('/admin/homepage-sections/reorder', payload);
  },
};

export const bannerSlidesApi = {
  list: async (sectionId: number) => {
    const res = await apiClient.get<{ data: HomepageBannerSlide[] }>(
      `/admin/homepage-sections/${sectionId}/banner-slides`,
    );
    return res.data.data;
  },
  create: async (sectionId: number, values: BannerSlideFormValues) => {
    const res = await apiClient.post<SingleResponse<HomepageBannerSlide>>(
      `/admin/homepage-sections/${sectionId}/banner-slides`,
      values,
    );
    return res.data.data;
  },
  update: async (sectionId: number, slideId: number, values: Partial<BannerSlideFormValues>) => {
    const res = await apiClient.patch<SingleResponse<HomepageBannerSlide>>(
      `/admin/homepage-sections/${sectionId}/banner-slides/${slideId}`,
      values,
    );
    return res.data.data;
  },
  delete: async (sectionId: number, slideId: number) => {
    await apiClient.delete(`/admin/homepage-sections/${sectionId}/banner-slides/${slideId}`);
  },
  reorder: async (sectionId: number, payload: ReorderPayload) => {
    await apiClient.post(`/admin/homepage-sections/${sectionId}/banner-slides/reorder`, payload);
  },
};
