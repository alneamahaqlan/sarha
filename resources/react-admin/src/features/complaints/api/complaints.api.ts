import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type {
  Complaint,
  ComplaintFormValues,
  ComplaintPriority,
  ComplaintSource,
  ComplaintStatus,
  ComplaintType,
} from '../types';

export interface ComplaintListParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  filter?: {
    status?: ComplaintStatus;
    type?: ComplaintType;
    priority?: ComplaintPriority;
    source?: ComplaintSource;
    clinic_id?: number;
  };
}

function buildParams(p: ComplaintListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.sort) params.sort = p.sort;
  if (p.filter?.status) params['filter[status]'] = p.filter.status;
  if (p.filter?.type) params['filter[type]'] = p.filter.type;
  if (p.filter?.priority) params['filter[priority]'] = p.filter.priority;
  if (p.filter?.source) params['filter[source]'] = p.filter.source;
  if (p.filter?.clinic_id) params['filter[clinic_id]'] = p.filter.clinic_id;
  return params;
}

export const complaintsApi = {
  list: async (params: ComplaintListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<Complaint>>('/admin/complaints', { params: buildParams(params) });
    return res.data;
  },
  get: async (id: number) => {
    const res = await apiClient.get<SingleResponse<Complaint>>(`/admin/complaints/${id}`);
    return res.data.data;
  },
  create: async (values: ComplaintFormValues) => {
    const res = await apiClient.post<SingleResponse<Complaint>>('/admin/complaints', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<ComplaintFormValues>) => {
    const res = await apiClient.patch<SingleResponse<Complaint>>(`/admin/complaints/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/admin/complaints/${id}`);
  },
  markInReview: async (id: number) => {
    const res = await apiClient.post<SingleResponse<Complaint>>(`/admin/complaints/${id}/mark-in-review`);
    return res.data.data;
  },
  resolve: async (id: number, resolution: string) => {
    const res = await apiClient.post<SingleResponse<Complaint>>(`/admin/complaints/${id}/resolve`, { resolution });
    return res.data.data;
  },
  reject: async (id: number, admin_notes: string) => {
    const res = await apiClient.post<SingleResponse<Complaint>>(`/admin/complaints/${id}/reject`, { admin_notes });
    return res.data.data;
  },
  notifyClinic: async (id: number) => {
    const res = await apiClient.post<SingleResponse<Complaint>>(`/admin/complaints/${id}/notify-clinic`);
    return res.data.data;
  },
  reply: async (id: number, reply: string) => {
    const res = await apiClient.post<SingleResponse<Complaint>>(`/admin/complaints/${id}/reply`, { reply });
    return res.data.data;
  },
  reopen: async (id: number) => {
    const res = await apiClient.post<SingleResponse<Complaint>>(`/admin/complaints/${id}/reopen`);
    return res.data.data;
  },
};
