import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicRewardsApi } from './api';
import type { GrantRewardPayload, RewardRulePayload, VoucherFilters } from './types';

const KEY = ['clinic', 'rewards'] as const;

export function useRewardRule() {
  return useQuery({ queryKey: [...KEY, 'rule'], queryFn: clinicRewardsApi.rule });
}

export function useUpdateRewardRule() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: RewardRulePayload) => clinicRewardsApi.updateRule(v),
    onSuccess: (data) => qc.setQueryData([...KEY, 'rule'], data),
  });
}

export function useRewardVouchers(filters: VoucherFilters = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', filters],
    queryFn: () => clinicRewardsApi.list(filters),
  });
}

export function useGrantReward() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: GrantRewardPayload) => clinicRewardsApi.grant(v),
    onSuccess: () => qc.invalidateQueries({ queryKey: [...KEY, 'list'] }),
  });
}

export function useRedeemReward() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: { id: number; bookingId?: number | null }) =>
      clinicRewardsApi.redeem(input.id, input.bookingId),
    onSuccess: () => qc.invalidateQueries({ queryKey: [...KEY, 'list'] }),
  });
}
