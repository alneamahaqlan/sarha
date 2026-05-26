import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { Clinic, ClinicPlan, ClinicStatus } from '../types';

export type TrashedFilter = 'with' | 'without' | 'only';

export interface ClinicListParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  filter?: {
    status?: ClinicStatus;
    subscription_type?: ClinicPlan;
    city_id?: number;
    trashed?: TrashedFilter;
  };
}

function buildParams(p: ClinicListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.sort) params.sort = p.sort;
  if (p.filter?.status) params['filter[status]'] = p.filter.status;
  if (p.filter?.subscription_type) params['filter[subscription_type]'] = p.filter.subscription_type;
  if (p.filter?.city_id) params['filter[city_id]'] = p.filter.city_id;
  if (p.filter?.trashed) params['filter[trashed]'] = p.filter.trashed;
  return params;
}

export interface ClinicFormValues {
  name: string;
  slug?: string | null;
  phone: string;
  email?: string | null;
  password?: string;
  city_id: number;
  address?: string | null;
  description?: string | null;
  status: ClinicStatus;
  subscription_type?: ClinicPlan | null;
  subscription_starts_at?: string | null;
  subscription_ends_at?: string | null;
  is_featured?: boolean;
  rejection_reason?: string | null;
  website?: string | null;
  instagram?: string | null;
  twitter?: string | null;
  snapchat?: string | null;
  google_place_id?: string | null;
  logo?: string | null;
  gallery?: string[];
  categories?: number[];
}

export interface ClinicImportSummary {
  created: number;
  updated: number;
  skipped: number;
}

export type ClinicStatsPeriod = 7 | 14 | 30;

export interface ClinicStatsTrendPoint {
  date: string;
  page_views: number;
  bookings: number;
  quote_requests: number;
}

export interface ClinicStatsData {
  cards: {
    page_views: number;
    bookings: number;
    quote_requests: number;
    search_appearances: number;
  };
  trend: ClinicStatsTrendPoint[];
  kpis: {
    avg_bookings_platform: number;
    this_clinic_bookings: number;
  };
}

export const clinicsApi = {
  list: async (params: ClinicListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<Clinic>>('/admin/clinics', { params: buildParams(params) });
    return res.data;
  },
  get: async (id: number) => {
    const res = await apiClient.get<SingleResponse<Clinic>>(`/admin/clinics/${id}`);
    return res.data.data;
  },
  create: async (values: ClinicFormValues) => {
    const res = await apiClient.post<SingleResponse<Clinic>>('/admin/clinics', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<ClinicFormValues>) => {
    const res = await apiClient.patch<SingleResponse<Clinic>>(`/admin/clinics/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/admin/clinics/${id}`);
  },

  // Actions
  approve: async (id: number) => {
    const res = await apiClient.post<SingleResponse<Clinic>>(`/admin/clinics/${id}/approve`);
    return res.data.data;
  },
  reject: async (id: number, reason: string) => {
    const res = await apiClient.post<SingleResponse<Clinic>>(`/admin/clinics/${id}/reject`, {
      rejection_reason: reason,
    });
    return res.data.data;
  },
  activate: async (id: number) => {
    const res = await apiClient.post<SingleResponse<Clinic>>(`/admin/clinics/${id}/activate`);
    return res.data.data;
  },
  suspend: async (id: number) => {
    const res = await apiClient.post<SingleResponse<Clinic>>(`/admin/clinics/${id}/suspend`);
    return res.data.data;
  },
  extend: async (id: number, days: 30 | 90) => {
    const res = await apiClient.post<SingleResponse<Clinic>>(`/admin/clinics/${id}/extend`, { days });
    return res.data.data;
  },
  impersonate: async (id: number) => {
    const res = await apiClient.post<{ data: { redirect: string; clinic: Clinic } }>(
      `/admin/clinics/${id}/impersonate`,
    );
    return res.data.data;
  },
  restore: async (id: number) => {
    const res = await apiClient.post<SingleResponse<Clinic>>(`/admin/clinics/${id}/restore`);
    return res.data.data;
  },
  bulk: async (action: 'delete' | 'restore' | 'force_delete', ids: number[]) => {
    const res = await apiClient.post<{ data: { affected: number } }>(`/admin/clinics/bulk`, {
      action,
      ids,
    });
    return res.data.data;
  },
  importSheet: async (url: string) => {
    const res = await apiClient.post<{ data: ClinicImportSummary }>(`/admin/clinics/import-sheet`, { url });
    return res.data.data;
  },
  stats: async (id: number, period: ClinicStatsPeriod) => {
    const res = await apiClient.get<{ data: ClinicStatsData }>(`/admin/clinics/${id}/stats`, {
      params: { period },
    });
    return res.data.data;
  },
  structure: async (id: number) => {
    const res = await apiClient.get<{ data: ClinicStructureData }>(`/admin/clinics/${id}/structure`);
    return res.data.data;
  },
};

// ----- Complex → clinic → services tree (admin view) -----
export interface StructureService {
  id: number;
  name: string;
  price: number | null;
  old_price: number | null;
  is_active: boolean;
}

export interface StructureSubClinic {
  id: number;
  name: string;
  name_en: string | null;
  description: string | null;
  is_active: boolean;
  sort_order: number;
  category: { id: number; name: string; emoji: string | null } | null;
  services_count: number;
  services: StructureService[];
}

export interface ClinicStructureData {
  clinic: { id: number; name: string };
  sub_clinics: StructureSubClinic[];
  general_services: StructureService[];
  totals: { sub_clinics: number; services: number };
}
