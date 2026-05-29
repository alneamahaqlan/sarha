import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';

export type AdminReportType = 'technical' | 'abusive_customer' | 'fake_review' | 'billing' | 'suggestion' | 'other';
export type AdminReportPriority = 'low' | 'medium' | 'high';
export type AdminReportStatus = 'new' | 'in_review' | 'resolved' | 'rejected';

export interface AdminClinicReport {
  id: number;
  reference_code: string;
  clinic: { id: number; name: string } | null;
  type: AdminReportType;
  priority: AdminReportPriority;
  status: AdminReportStatus;
  subject: string;
  description: string;
  admin_notes: string | null;
  resolution: string | null;
  resolved_at: string | null;
  created_at: string | null;
}

export interface UpdateReportPayload {
  status?: AdminReportStatus;
  priority?: AdminReportPriority;
  admin_notes?: string | null;
  resolution?: string | null;
}

export const adminClinicReportsApi = {
  list: async (filter: { status?: string; type?: string } = {}) => {
    const params: Record<string, string> = {};
    if (filter.status) params['filter[status]'] = filter.status;
    if (filter.type)   params['filter[type]']   = filter.type;
    const res = await apiClient.get<PaginatedResponse<AdminClinicReport>>('/admin/clinic-reports', { params });
    return res.data;
  },
  update: async (id: number, payload: UpdateReportPayload) => {
    const res = await apiClient.patch<SingleResponse<AdminClinicReport>>(`/admin/clinic-reports/${id}`, payload);
    return res.data.data;
  },
};
