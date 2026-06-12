import { apiClient } from '@/lib/api-client';

export type ManagedStatus = 'submitted' | 'closed';

export interface CampaignRequest {
  id: number;
  name: string;
  managed_status: ManagedStatus | null;
  total_recipients: number;
  image_url: string | null;
  clinic: { id: number; name: string } | null;
  created_at: string | null;
}

export interface CampaignRequestDetail extends CampaignRequest {
  message_template: string;
  audience: Record<string, unknown> | null;
  closed_by: string | null;
  closed_at: string | null;
}

export interface CampaignRequestListParams {
  page?: number;
  filter?: { status?: string };
}

interface ListMeta { current_page: number; last_page: number; total: number; from: number; to: number }

export const campaignRequestsApi = {
  list: async (params: CampaignRequestListParams = {}) => {
    const query: Record<string, string | number> = {};
    if (params.page) query.page = params.page;
    if (params.filter?.status) query['filter[status]'] = params.filter.status;
    const res = await apiClient.get<{ data: CampaignRequest[]; meta: ListMeta }>(
      '/admin/campaign-requests', { params: query },
    );
    return res.data;
  },

  get: async (id: number) => {
    const res = await apiClient.get<{ data: CampaignRequestDetail }>(`/admin/campaign-requests/${id}`);
    return res.data.data;
  },

  close: async (id: number) => {
    const res = await apiClient.post<{ message: string }>(`/admin/campaign-requests/${id}/close`);
    return res.data;
  },

  /** Downloads the recipient CSV (name + phone) so staff can run it externally. */
  exportCsv: async (id: number, filename: string) => {
    const res = await apiClient.get(`/admin/campaign-requests/${id}/export`, { responseType: 'blob' });
    const url = URL.createObjectURL(res.data as Blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  },
};
