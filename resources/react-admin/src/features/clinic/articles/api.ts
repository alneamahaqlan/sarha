import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';

export interface ClinicArticleUsage {
  published_this_month: number;
  monthly_limit: number | null;
  is_premium: boolean;
}

export type ClinicArticleList = PaginatedResponse<ClinicArticle> & {
  usage?: ClinicArticleUsage;
};

export interface ClinicArticle {
  id: number;
  clinic_id: number;
  title: string;
  slug: string;
  meta_description: string | null;
  body: string;
  word_count: number;
  cover_image: string | null;
  tags: string[] | null;
  is_published: boolean;
  ai_generated: boolean;
  views_count: number;
  published_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface ClinicArticleFormValues {
  title: string;
  slug?: string;
  body: string;
  meta_description?: string | null;
  cover_image?: string | null;
  is_published?: boolean;
}

export interface ListParams {
  page?: number;
  per_page?: number;
  search?: string;
}

function buildParams(p: ListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  return params;
}

export const clinicArticlesApi = {
  list: async (params: ListParams = {}) => {
    const res = await apiClient.get<ClinicArticleList>('/clinic/articles', { params: buildParams(params) });
    return res.data;
  },
  get: async (id: number) => {
    const res = await apiClient.get<SingleResponse<ClinicArticle>>(`/clinic/articles/${id}`);
    return res.data.data;
  },
  create: async (values: ClinicArticleFormValues) => {
    const res = await apiClient.post<SingleResponse<ClinicArticle>>('/clinic/articles', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<ClinicArticleFormValues>) => {
    const res = await apiClient.patch<SingleResponse<ClinicArticle>>(`/clinic/articles/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/clinic/articles/${id}`);
  },
  generateAi: async (kind: 'excerpt' | 'article', title: string) => {
    const res = await apiClient.post<{ data: { text: string } }>('/clinic/articles/generate-ai', { kind, title });
    return res.data.data.text;
  },
};
