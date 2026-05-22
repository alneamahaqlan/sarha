import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse } from '@/types/api';
import type { AuditLog } from '../types';

export interface AuditLogListParams {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
  filter?: { admin_id?: number };
}

function buildParams(p: AuditLogListParams) {
  const params: Record<string, string | number | boolean> = {};
  if (p.page) params.page = p.page;
  if (p.per_page) params.per_page = p.per_page;
  if (p.search) params.search = p.search;
  if (p.sort) params.sort = p.sort;
  if (p.filter?.admin_id) params['filter[admin_id]'] = p.filter.admin_id;
  return params;
}

export const auditLogsApi = {
  list: async (params: AuditLogListParams = {}) => {
    const res = await apiClient.get<PaginatedResponse<AuditLog>>('/admin/audit-logs', { params: buildParams(params) });
    return res.data;
  },
};
