import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type {
  GrantRewardPayload,
  RewardRule,
  RewardRulePayload,
  RewardVoucher,
  VoucherFilters,
} from './types';

export const clinicRewardsApi = {
  rule: async (): Promise<RewardRule> =>
    (await apiClient.get<{ data: RewardRule }>('/clinic/rewards/rule')).data.data,

  updateRule: async (v: RewardRulePayload): Promise<RewardRule> =>
    (await apiClient.put<{ data: RewardRule }>('/clinic/rewards/rule', v)).data.data,

  list: async (filters: VoucherFilters = {}): Promise<PaginatedResponse<RewardVoucher>> => {
    const params: Record<string, string | number> = {};
    if (filters.status) params.status = filters.status;
    if (filters.search) params.search = filters.search;
    if (filters.page) params.page = filters.page;
    if (filters.per_page) params.per_page = filters.per_page;
    return (await apiClient.get<PaginatedResponse<RewardVoucher>>('/clinic/rewards', { params })).data;
  },

  grant: async (v: GrantRewardPayload): Promise<RewardVoucher> =>
    (await apiClient.post<SingleResponse<RewardVoucher>>('/clinic/rewards', v)).data.data,

  redeem: async (id: number, bookingId?: number | null): Promise<RewardVoucher> =>
    (await apiClient.post<SingleResponse<RewardVoucher>>(
      `/clinic/rewards/${id}/redeem`,
      bookingId ? { booking_id: bookingId } : {},
    )).data.data,
};
