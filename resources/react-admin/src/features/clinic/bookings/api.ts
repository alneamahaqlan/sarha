import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type { Booking, BookingStatus } from '@/features/bookings/types';

export interface ClinicBookingUpdateValues {
  status?: BookingStatus;
  appointment_at?: string | null;
  clinic_notes?: string | null;
}

export interface ListParams {
  page?: number;
  per_page?: number;
  search?: string;
  filter?: { status?: BookingStatus };
}

function buildParams(p: ListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.filter?.status) params['filter[status]'] = p.filter.status;
  return params;
}

export const clinicBookingsApi = {
  list: async (params: ListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<Booking>>('/clinic/bookings', { params: buildParams(params) });
    return res.data;
  },
  update: async (id: number, values: ClinicBookingUpdateValues) => {
    const res = await apiClient.patch<SingleResponse<Booking>>(`/clinic/bookings/${id}`, values);
    return res.data.data;
  },
};
