import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';

export type CustomerReportType = 'bug' | 'suggestion' | 'clinic_concern' | 'inappropriate' | 'other';
export type CustomerReportPriority = 'low' | 'medium' | 'high';
export type CustomerReportStatus = 'new' | 'in_review' | 'resolved' | 'rejected';

export interface AdminCustomerReport {
  id: number;
  reference_code: string;
  user: { id: number; name: string; phone: string } | null;
  clinic: { id: number; name: string } | null;
  type: CustomerReportType;
  priority: CustomerReportPriority;
  status: CustomerReportStatus;
  subject: string;
  description: string;
  admin_notes: string | null;
  resolution: string | null;
  resolved_at: string | null;
  created_at: string | null;
}

export interface UpdateCustomerReportPayload {
  status?: CustomerReportStatus;
  priority?: CustomerReportPriority;
  admin_notes?: string | null;
  resolution?: string | null;
}

export const adminCustomerReportsApi = {
  list: async (filter: { status?: string; type?: string } = {}) => {
    const params: Record<string, string> = {};
    if (filter.status) params['filter[status]'] = filter.status;
    if (filter.type)   params['filter[type]']   = filter.type;
    const res = await apiClient.get<PaginatedResponse<AdminCustomerReport>>('/admin/customer-reports', { params });
    return res.data;
  },
  update: async (id: number, payload: UpdateCustomerReportPayload) => {
    const res = await apiClient.patch<SingleResponse<AdminCustomerReport>>(`/admin/customer-reports/${id}`, payload);
    return res.data.data;
  },
};
