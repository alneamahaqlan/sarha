import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';

export type ComplaintType = 'quality' | 'pricing' | 'misleading_info' | 'other';
export type ComplaintStatus = 'new' | 'in_review' | 'resolved' | 'rejected';

export interface ClinicComplaint {
  id: number;
  reference_code: string;
  type: ComplaintType;
  status: ComplaintStatus;
  priority: 'low' | 'medium' | 'high';
  subject: string;
  description: string;
  resolution: string | null;
  created_at: string | null;
}

export interface ClinicComplaintFormValues {
  type: ComplaintType;
  priority?: 'low' | 'medium' | 'high';
  subject: string;
  description: string;
}

export const clinicComplaintsApi = {
  list: async (status?: string) => {
    const params: Record<string, string> = {};
    if (status) params['filter[status]'] = status;
    const res = await apiClient.get<PaginatedResponse<ClinicComplaint>>('/clinic/complaints', { params });
    return res.data;
  },
  create: async (values: ClinicComplaintFormValues) => {
    const res = await apiClient.post<SingleResponse<ClinicComplaint>>('/clinic/complaints', values);
    return res.data.data;
  },
};
