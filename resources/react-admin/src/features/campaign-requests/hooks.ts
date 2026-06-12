import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { campaignRequestsApi, type CampaignRequestListParams } from './api';

const KEY = ['admin', 'campaign-requests'] as const;

export function useCampaignRequests(params: CampaignRequestListParams = {}) {
  return useQuery({
    queryKey: [...KEY, params],
    queryFn: () => campaignRequestsApi.list(params),
  });
}

export function useCampaignRequest(id: number | null) {
  return useQuery({
    enabled: id !== null,
    queryKey: [...KEY, 'detail', id],
    queryFn: () => campaignRequestsApi.get(id as number),
  });
}

export function useCloseCampaignRequest() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => campaignRequestsApi.close(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEY });
      qc.invalidateQueries({ queryKey: ['admin', 'nav-badges'] });
    },
  });
}
