import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse } from '@/types/api';
import type {
  CustomerBookingRow,
  CustomerComplaintRow,
  CustomerListRow,
  CustomerNote,
  CustomerProfile,
  CustomerStats,
  ListFilters,
  TimelineEvent,
} from './types';

function flatten(f: ListFilters): Record<string, string | number | boolean> {
  const out: Record<string, string | number | boolean> = {};
  if (f.search) out.search = f.search;
  if (f.segment) out.segment = f.segment;
  if (f.booking_range) out.booking_range = f.booking_range;
  if (f.has_notes) out.has_notes = '1';
  if (f.last_interaction) out.last_interaction = f.last_interaction;
  if (f.page) out.page = f.page;
  if (f.per_page) out.per_page = f.per_page;
  return out;
}

export const customersApi = {
  list: async (filters: ListFilters = {}) => {
    const res = await apiClient.get<PaginatedResponse<CustomerListRow>>('/clinic/customers', {
      params: flatten(filters),
    });
    return res.data;
  },

  stats: async () => {
    const res = await apiClient.get<{ data: CustomerStats }>('/clinic/customers/stats');
    return res.data.data;
  },

  show: async (id: number) => {
    const res = await apiClient.get<{ data: CustomerProfile }>(`/clinic/customers/${id}`);
    return res.data.data;
  },

  update: async (id: number, payload: { name?: string; email?: string | null }) => {
    const res = await apiClient.patch<{ data: CustomerProfile }>(`/clinic/customers/${id}`, payload);
    return res.data.data;
  },

  bookings: async (id: number) => {
    const res = await apiClient.get<{ data: CustomerBookingRow[] }>(`/clinic/customers/${id}/bookings`);
    return res.data.data;
  },

  complaints: async (id: number) => {
    const res = await apiClient.get<{ data: CustomerComplaintRow[] }>(`/clinic/customers/${id}/complaints`);
    return res.data.data;
  },

  timeline: async (id: number, limit = 50) => {
    const res = await apiClient.get<{ data: TimelineEvent[] }>(`/clinic/customers/${id}/timeline`, {
      params: { limit },
    });
    return res.data.data;
  },

  // Notes
  notes: async (id: number) => {
    const res = await apiClient.get<{ data: CustomerNote[] }>(`/clinic/customers/${id}/notes`);
    return res.data.data;
  },
  createNote: async (id: number, body: string, isPinned = false) => {
    const res = await apiClient.post<{ data: CustomerNote }>(`/clinic/customers/${id}/notes`, {
      body,
      is_pinned: isPinned,
    });
    return res.data.data;
  },
  updateNote: async (
    id: number,
    noteId: number,
    payload: { body?: string; is_pinned?: boolean },
  ) => {
    const res = await apiClient.patch<{ data: CustomerNote }>(
      `/clinic/customers/${id}/notes/${noteId}`,
      payload,
    );
    return res.data.data;
  },
  deleteNote: async (id: number, noteId: number) => {
    await apiClient.delete(`/clinic/customers/${id}/notes/${noteId}`);
  },
};
