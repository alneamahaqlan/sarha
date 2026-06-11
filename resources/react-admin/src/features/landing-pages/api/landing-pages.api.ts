import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { LandingPage, LandingPageBlock, LandingPageFormValues, SeoFormValues, BlockType } from '../types';

export interface LandingPageListParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  filter?: { type?: string; status?: string };
}

function buildParams(p: LandingPageListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.sort) params.sort = p.sort;
  if (p.filter?.type) params['filter[type]'] = p.filter.type;
  if (p.filter?.status) params['filter[status]'] = p.filter.status;
  return params;
}

export interface ReorderPayload {
  order: { id: number; sort_order: number }[];
}

export const landingPagesApi = {
  list: async (params: LandingPageListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<LandingPage>>('/admin/landing-pages', { params: buildParams(params) });
    return res.data;
  },
  get: async (id: number) => {
    const res = await apiClient.get<SingleResponse<LandingPage>>(`/admin/landing-pages/${id}`);
    return res.data.data;
  },
  create: async (values: LandingPageFormValues) => {
    const res = await apiClient.post<SingleResponse<LandingPage>>('/admin/landing-pages', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<LandingPageFormValues & SeoFormValues>) => {
    const res = await apiClient.patch<SingleResponse<LandingPage>>(`/admin/landing-pages/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/admin/landing-pages/${id}`);
  },

  // ── Blocks ──
  blocks: async (pageId: number) => {
    const res = await apiClient.get<{ data: LandingPageBlock[] }>(`/admin/landing-pages/${pageId}/blocks`);
    return res.data.data;
  },
  addBlock: async (pageId: number, type: BlockType) => {
    const res = await apiClient.post<SingleResponse<LandingPageBlock>>(`/admin/landing-pages/${pageId}/blocks`, { type });
    return res.data.data;
  },
  updateBlock: async (pageId: number, blockId: number, values: Partial<Pick<LandingPageBlock, 'is_visible' | 'sort_order' | 'config'>>) => {
    const res = await apiClient.patch<SingleResponse<LandingPageBlock>>(`/admin/landing-pages/${pageId}/blocks/${blockId}`, values);
    return res.data.data;
  },
  deleteBlock: async (pageId: number, blockId: number) => {
    await apiClient.delete(`/admin/landing-pages/${pageId}/blocks/${blockId}`);
  },
  reorderBlocks: async (pageId: number, payload: ReorderPayload) => {
    await apiClient.post(`/admin/landing-pages/${pageId}/blocks/reorder`, payload);
  },

  stats: async (pageId: number, range?: { from?: string; to?: string }) => {
    const res = await apiClient.get<{ data: LandingStats }>(`/admin/landing-pages/${pageId}/stats`, { params: range });
    return res.data.data;
  },

  generate: async (input: { clinic_id?: number | null; service?: string; city_id?: number | null; category_id?: number | null }) => {
    const res = await apiClient.post<{ data: AiDraft }>('/admin/landing-pages/generate', input);
    return res.data.data;
  },

  customers: async (pageId: number, params: { page?: number; per_page?: number; search?: string; status?: string } = {}) => {
    const p: Record<string, string | number> = {};
    if (params.page) p.page = params.page;
    if (params.per_page) p.per_page = params.per_page;
    if (params.search) p.search = params.search;
    if (params.status) p['filter[status]'] = params.status;
    const res = await apiClient.get<LandingCustomersResponse>(`/admin/landing-pages/${pageId}/customers`, { params: p });
    return res.data;
  },
};

export interface LandingCustomerRow {
  id: number;
  reference_code: string;
  customer_name: string;
  customer_phone: string;
  service: string | null;
  status: string;
  acquisition: string | null;
  utm_source: string | null;
  utm_campaign: string | null;
  is_registered: boolean;
  customer_id: number | null;
  created_at: string | null;
}

export interface LandingCustomersResponse {
  data: LandingCustomerRow[];
  meta: { current_page: number; from: number | null; to: number | null; last_page: number; per_page: number; total: number };
  totals: { total: number; registered: number; completed: number };
}

export interface AiDraft {
  title: string;
  description: string;
  faq: { q: string; a: string }[];
  seo_title: string;
  seo_description: string;
  keywords: string;
  cta: string;
}

export interface LandingStats {
  totals: {
    page_views: number;
    unique_visitors: number;
    clicks: number;
    whatsapp_clicks: number;
    calls: number;
    bookings: number;
    conversions: number;
    conversion_rate: number;
    bounce_rate: number;
    avg_session_sec: number;
  };
  trend: { date: string; page_views: number; unique: number; conversions: number }[];
  sources: { source: string; total: number }[];
}
