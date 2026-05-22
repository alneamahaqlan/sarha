import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';

export interface AdminArticle {
  id: number;
  clinic_id: number;
  clinic?: { id: number; name: string };
  title: string;
  slug: string;
  meta_description: string | null;
  body: string;
  cover_image: string | null;
  tags: string[] | null;
  is_published: boolean;
  ai_generated: boolean;
  views_count: number;
  published_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface AdminArticleFormValues {
  clinic_id: number;
  title: string;
  slug?: string;
  body: string;
  meta_description?: string | null;
  cover_image?: string | null;
  is_published?: boolean;
  ai_generated?: boolean;
}

export interface ListParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  filter?: {
    clinic_id?: number;
    is_published?: boolean;
  };
}

function buildParams(p: ListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.sort) params.sort = p.sort;
  if (p.filter?.clinic_id) params['filter[clinic_id]'] = p.filter.clinic_id;
  if (typeof p.filter?.is_published === 'boolean') params['filter[is_published]'] = p.filter.is_published;
  return params;
}

export const adminArticlesApi = {
  list: async (params: ListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<AdminArticle>>('/admin/articles', { params: buildParams(params) });
    return res.data;
  },
  get: async (id: number) => {
    const res = await apiClient.get<SingleResponse<AdminArticle>>(`/admin/articles/${id}`);
    return res.data.data;
  },
  create: async (values: AdminArticleFormValues) => {
    const res = await apiClient.post<SingleResponse<AdminArticle>>('/admin/articles', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<AdminArticleFormValues>) => {
    const res = await apiClient.patch<SingleResponse<AdminArticle>>(`/admin/articles/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/admin/articles/${id}`);
  },
};
