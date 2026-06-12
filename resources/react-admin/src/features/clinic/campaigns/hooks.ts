import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { campaignsApi } from './api';
import type { CreateCampaignInput, RecipientStatus } from './types';

const KEY = ['clinic', 'campaigns'] as const;

export function useCampaigns() {
  return useQuery({
    queryKey: [...KEY, 'list'],
    queryFn: () => campaignsApi.list(),
    staleTime: 15_000,
  });
}

export function useCampaign(id: number | null) {
  return useQuery({
    enabled: !!id,
    queryKey: [...KEY, 'show', id],
    queryFn: () => campaignsApi.get(id!),
  });
}

export function useCampaignRecipients(id: number | null, status?: RecipientStatus) {
  return useQuery({
    enabled: !!id,
    queryKey: [...KEY, 'recipients', id, status ?? 'all'],
    queryFn: () => campaignsApi.recipients(id!, status),
  });
}

export function useCreateCampaign() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateCampaignInput) => campaignsApi.create(payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useMarkRecipient(campaignId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ recipientId, status }: { recipientId: number; status: RecipientStatus }) =>
      campaignsApi.mark(campaignId, recipientId, status),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [...KEY, 'recipients', campaignId] });
      qc.invalidateQueries({ queryKey: [...KEY, 'show', campaignId] });
      qc.invalidateQueries({ queryKey: [...KEY, 'list'] });
    },
  });
}

export function useDeleteCampaign() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => campaignsApi.remove(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
