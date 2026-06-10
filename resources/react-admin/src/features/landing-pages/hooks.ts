import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { landingPagesApi, type LandingPageListParams, type ReorderPayload } from './api/landing-pages.api';
import type { BlockType, LandingPageBlock, LandingPageFormValues, SeoFormValues } from './types';

const KEY = ['admin', 'landing-pages'] as const;

export function useLandingPages(params: LandingPageListParams = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', params],
    queryFn: () => landingPagesApi.list(params),
  });
}

export function useLandingPage(id: number | null) {
  return useQuery({
    queryKey: [...KEY, 'detail', id],
    queryFn: () => landingPagesApi.get(id as number),
    enabled: id !== null,
  });
}

export function useCreateLandingPage() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: LandingPageFormValues) => landingPagesApi.create(values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateLandingPage(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<LandingPageFormValues & SeoFormValues>) => landingPagesApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteLandingPage() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => landingPagesApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

// ── Blocks ──

const blocksKey = (pageId: number) => [...KEY, 'blocks', pageId] as const;

export function useLandingPageBlocks(pageId: number | null) {
  return useQuery({
    queryKey: blocksKey(pageId ?? 0),
    queryFn: () => landingPagesApi.blocks(pageId as number),
    enabled: pageId !== null,
  });
}

export function useAddBlock(pageId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (type: BlockType) => landingPagesApi.addBlock(pageId, type),
    onSuccess: () => qc.invalidateQueries({ queryKey: blocksKey(pageId) }),
  });
}

export function useUpdateBlock(pageId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (args: { blockId: number; values: Partial<Pick<LandingPageBlock, 'is_visible' | 'sort_order' | 'config'>> }) =>
      landingPagesApi.updateBlock(pageId, args.blockId, args.values),
    onSuccess: () => qc.invalidateQueries({ queryKey: blocksKey(pageId) }),
  });
}

export function useDeleteBlock(pageId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (blockId: number) => landingPagesApi.deleteBlock(pageId, blockId),
    onSuccess: () => qc.invalidateQueries({ queryKey: blocksKey(pageId) }),
  });
}

export function useLandingStats(pageId: number | null, range?: { from?: string; to?: string }) {
  return useQuery({
    queryKey: [...KEY, 'stats', pageId, range],
    queryFn: () => landingPagesApi.stats(pageId as number, range),
    enabled: pageId !== null,
  });
}

export function useReorderBlocks(pageId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: ReorderPayload) => landingPagesApi.reorderBlocks(pageId, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: blocksKey(pageId) }),
  });
}

export function useGenerateLanding() {
  return useMutation({
    mutationFn: (input: { clinic_id?: number | null; service?: string; city_id?: number | null; category_id?: number | null }) =>
      landingPagesApi.generate(input),
  });
}
