import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { Booking, BookingFormValues, BookingStatus } from '../types';

export type TrashedFilter = 'with' | 'without' | 'only';

export interface BookingListParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  filter?: { status?: BookingStatus; clinic_id?: number; trashed?: TrashedFilter };
}

function buildParams(p: BookingListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.sort) params.sort = p.sort;
  if (p.filter?.status) params['filter[status]'] = p.filter.status;
  if (p.filter?.clinic_id) params['filter[clinic_id]'] = p.filter.clinic_id;
  if (p.filter?.trashed) params['filter[trashed]'] = p.filter.trashed;
  return params;
}

export const bookingsApi = {
  list: async (params: BookingListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<Booking>>('/admin/bookings', { params: buildParams(params) });
    return res.data;
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
