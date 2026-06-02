import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { Booking, BookingFormValues, BookingStatus } from '../types';

export type TrashedFilter = 'with' | 'without' | 'only';

export interface BookingListParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  filter?: { status?: BookingStatus; clinic_id?: number; sub_clinic_id?: number; service_id?: number; trashed?: TrashedFilter };
}

function buildParams(p: BookingListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.sort) params.sort = p.sort;
  if (p.filter?.status) params['filter[status]'] = p.filter.status;
  if (p.filter?.clinic_id) params['filter[clinic_id]'] = p.filter.clinic_id;
  if (p.filter?.sub_clinic_id) params['filter[sub_clinic_id]'] = p.filter.sub_clinic_id;
  if (p.filter?.service_id) params['filter[service_id]'] = p.filter.service_id;
  if (p.filter?.trashed) params['filter[trashed]'] = p.filter.trashed;
  return params;
}

export type BookingStatusCounts = { all: number } & Record<BookingStatus, number>;

export interface BookingExportOptions {
  statuses?: BookingStatus[];
  order?: 'asc' | 'desc';
}

/** Saves a Blob as a file via a temporary anchor. */
function triggerDownload(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

export const bookingsApi = {
  list: async (params: BookingListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<Booking>>('/admin/bookings', { params: buildParams(params) });
    return res.data;
  },
  statusCounts: async () => {
    const res = await apiClient.get<{ data: BookingStatusCounts }>('/admin/bookings/status-counts');
    return res.data.data;
  },
  exportCsv: async (params: BookingListParams, opts: BookingExportOptions) => {
    const query = { ...buildParams(params), order: opts.order ?? 'desc' } as Record<string, unknown>;
    if (opts.statuses && opts.statuses.length) query.statuses = opts.statuses;
    const res = await apiClient.get('/admin/bookings/export', { params: query, responseType: 'blob' });
    triggerDownload(res.data as Blob, `bookings-${new Date().toISOString().slice(0, 10)}.csv`);
  },
  get: async (id: number) => {
    const res = await apiClient.get<SingleResponse<Booking>>(`/admin/bookings/${id}`);
    return res.data.data;
  },
  create: async (values: BookingFormValues) => {
    const res = await apiClient.post<SingleResponse<Booking>>('/admin/bookings', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<BookingFormValues>) => {
    const res = await apiClient.patch<SingleResponse<Booking>>(`/admin/bookings/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/admin/bookings/${id}`);
  },
  restore: async (id: number) => {
    const res = await apiClient.post<SingleResponse<Booking>>(`/admin/bookings/${id}/restore`);
    return res.data.data;
  },
  forceDelete: async (id: number) => {
    await apiClient.delete(`/admin/bookings/${id}/force`);
  },
};
