import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';

export type ReportType = 'technical' | 'abusive_customer' | 'fake_review' | 'billing' | 'suggestion' | 'other';
export type ReportPriority = 'low' | 'medium' | 'high';
export type ReportStatus = 'new' | 'in_review' | 'resolved' | 'rejected';

export interface ClinicReport {
  id: number;
  reference_code: string;
  type: ReportType;
  priority: ReportPriority;
  status: ReportStatus;
  subject: string;
  description: string;
  admin_notes: string | null;
  resolution: string | null;
  resolved_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface ClinicReportFormValues {
  type: ReportType;
  priority?: ReportPriority;
  subject: string;
  description: string;
}

export const clinicReportsApi = {
  list: async (status?: string) => {
    const params: Record<string, string> = {};
    if (status) params['filter[status]'] = status;
    const res = await apiClient.get<PaginatedResponse<ClinicReport>>('/clinic/reports', { params });
    return res.data;
  },
  create: async (values: ClinicReportFormValues) => {
    const res = await apiClient.post<SingleResponse<ClinicReport>>('/clinic/reports', values);
    return res.data.data;
  },
};
