import { apiClient } from '@/lib/api-client';
import type {
  AssigneeKind,
  AssigneeOption,
  BookingActivity,
  BookingDetail,
  BookingStage,
  CreateBookingInput,
  CustomerProfile,
  KanbanBoard,
  KanbanFilters,
  KanbanStats,
  QuickAction,
  StageColor,
  StageKind,
  SuggestionSettings,
  TagColor,
  TagDto,
  TagLabelOption,
  UpdateBookingInput,
} from './types';

function flattenFilters(f: KanbanFilters, cursors?: Record<string, string | null>) {
  const out: Record<string, string | number | boolean> = {};
  if (f.search) out.search = f.search;
  if (f.service_id) out.service_id = f.service_id;
  if (f.sub_clinic_id) out.sub_clinic_id = f.sub_clinic_id;
  if (f.assignee_id) out.assignee_id = f.assignee_id;
  if (f.assignee_type) out.assignee_type = f.assignee_type;
  if (f.date_from) out.date_from = f.date_from;
  if (f.date_to) out.date_to = f.date_to;
  if (f.auto_tag) out.auto_tag = f.auto_tag;
  if (f.custom_tag) out.custom_tag = f.custom_tag;
  if (f.acquisition_source) out.acquisition_source = f.acquisition_source;
  if (f.mine_only) out.mine_only = '1';
  if (cursors) {
    for (const k of Object.keys(cursors)) {
      const v = cursors[k];
      if (v != null) out[`cursors[${k}]`] = v;
    }
  }
  return out;
}

export const bookingKanbanApi = {
  board: async (filters: KanbanFilters = {}, cursors?: Record<string, string | null>): Promise<KanbanBoard> => {
    const res = await apiClient.get<{ data: KanbanBoard }>('/clinic/bookings/kanban', {
      params: flattenFilters(filters, cursors),
    });
    return res.data.data;
  },

  stats: async (): Promise<KanbanStats> => {
    const res = await apiClient.get<{ data: KanbanStats }>('/clinic/bookings/kanban-stats');
    return res.data.data;
  },

  detail: async (id: number): Promise<BookingDetail> => {
    const res = await apiClient.get<{ data: BookingDetail }>(`/clinic/bookings/${id}/detail`);
    return res.data.data;
  },

  activities: async (id: number): Promise<BookingActivity[]> => {
    const res = await apiClient.get<{ data: BookingActivity[] }>(`/clinic/bookings/${id}/activities`);
    return res.data.data;
  },

  logActivity: async (id: number, action: QuickAction, payload?: { note?: string; outcome?: 'answered' | 'no_answer' }) => {
    const res = await apiClient.post<{ data: BookingActivity }>(`/clinic/bookings/${id}/activities`, {
      action,
      ...payload,
    });
    return res.data.data;
  },

  addTag: async (id: number, scope: 'booking' | 'customer', label: string, color: TagColor): Promise<TagDto> => {
    const res = await apiClient.post<{ data: TagDto }>(`/clinic/bookings/${id}/tags`, { scope, label, color });
    return res.data.data;
  },

  removeTag: async (id: number, scope: 'booking' | 'customer', tagId: number) => {
    await apiClient.delete(`/clinic/bookings/${id}/tags/${scope}/${tagId}`);
  },

  assignees: async (): Promise<AssigneeOption[]> => {
    const res = await apiClient.get<{ data: AssigneeOption[] }>('/clinic/bookings/assignees');
    return res.data.data;
  },

  assign: async (id: number, assigneeType: AssigneeKind | null, assigneeId: number | null) => {
    const res = await apiClient.patch(`/clinic/bookings/${id}/assignee`, {
      assignee_type: assigneeType,
      assignee_id: assigneeId,
    });
    return res.data;
  },

  customerProfile: async (phone: string): Promise<CustomerProfile> => {
    const res = await apiClient.get<{ data: CustomerProfile }>(`/clinic/customers/by-phone/${encodeURIComponent(phone)}`);
    return res.data.data;
  },

  // Customer notes moved to a thread endpoint in phase 3. See
  // features/clinic/customers/api.ts for the new createNote/
  // updateNote/deleteNote functions.

  updateStatus: async (id: number, payload: {
    status?: string;
    stage_id?: number | null;
    cancel_reason?: string;
    cancel_note?: string;
    completion_note?: string;
    appointment_at?: string | null;
    clinic_notes?: string | null;
  }) => {
    const res = await apiClient.patch(`/clinic/bookings/${id}`, payload);
    return res.data.data;
  },

  update: async (id: number, payload: UpdateBookingInput) => {
    const res = await apiClient.patch(`/clinic/bookings/${id}`, payload);
    return res.data.data;
  },

  create: async (payload: CreateBookingInput) => {
    const res = await apiClient.post('/clinic/bookings', payload);
    return res.data.data;
  },

  tagLabels: async (): Promise<TagLabelOption[]> => {
    const res = await apiClient.get<{ data: TagLabelOption[] }>('/clinic/bookings/tag-labels');
    return res.data.data;
  },

  stages: async (): Promise<BookingStage[]> => {
    const res = await apiClient.get<{ data: BookingStage[] }>('/clinic/bookings/stages');
    return res.data.data ?? [];
  },

  createStage: async (payload: { name: string; kind: StageKind; color: StageColor }): Promise<BookingStage> => {
    const res = await apiClient.post<{ data: BookingStage }>('/clinic/bookings/stages', payload);
    return res.data.data;
  },

  updateStage: async (id: number, payload: { name: string; kind: StageKind; color: StageColor }): Promise<BookingStage> => {
    const res = await apiClient.patch<{ data: BookingStage }>(`/clinic/bookings/stages/${id}`, payload);
    return res.data.data;
  },

  deleteStage: async (id: number): Promise<void> => {
    await apiClient.delete(`/clinic/bookings/stages/${id}`);
  },

  reorderStages: async (orderedIds: number[]): Promise<BookingStage[]> => {
    const res = await apiClient.put<{ data: BookingStage[] }>('/clinic/bookings/stages/reorder', { ordered_ids: orderedIds });
    return res.data.data ?? [];
  },

  suggestionSettings: async (): Promise<SuggestionSettings> => {
    const res = await apiClient.get<{ data: SuggestionSettings }>('/clinic/bookings/suggestion-settings');
    return res.data.data;
  },

  updateSuggestionSettings: async (payload: SuggestionSettings): Promise<SuggestionSettings> => {
    const res = await apiClient.put<{ data: SuggestionSettings }>('/clinic/bookings/suggestion-settings', payload);
    return res.data.data;
  },

  /**
   * Streams a CSV export honouring the current filters. `columns` is an
   * optional subset of Kanban columns (empty = all stages); `order` is
   * the date sort direction. Triggers a browser download.
   */
  exportCsv: async (filters: KanbanFilters, opts: { columns?: string[]; order?: 'asc' | 'desc' }) => {
    const params = { ...flattenFilters(filters), order: opts.order ?? 'desc' } as Record<string, unknown>;
    if (opts.columns && opts.columns.length) params.columns = opts.columns;
    const res = await apiClient.get('/clinic/bookings/export', { params, responseType: 'blob' });
    triggerDownload(res.data as Blob, `bookings-${new Date().toISOString().slice(0, 10)}.csv`);
  },
};

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
