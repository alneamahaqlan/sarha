import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type LandingPageListParams, type ReorderPayload } from './api/landing-pages.api';
import { useLandingScope } from './scope';
import type { BlockType, ChromeFormValues, LandingPageBlock, LandingPageFormValues, SeoFormValues } from './types';

/**
 * All hooks resolve their API client + query-cache namespace from the active
 * LandingScope (admin by default, clinic when wrapped in LandingScopeProvider),
 * so the admin + clinic panels share one set of hooks.
 */
export function useLandingPages(params: LandingPageListParams = {}) {
  const { api, keyBase } = useLandingScope();
  return useQuery({
    queryKey: [...keyBase, 'list', params],
    queryFn: () => api.list(params),
  });
}

export function useLandingPage(id: number | null) {
  const { api, keyBase } = useLandingScope();
  return useQuery({
    queryKey: [...keyBase, 'detail', id],
    queryFn: () => api.get(id as number),
    enabled: id !== null,
  });
}

export function useCreateLandingPage() {
  const { api, keyBase } = useLandingScope();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: LandingPageFormValues) => api.create(values),
    onSuccess: () => qc.invalidateQueries({ queryKey: keyBase }),
  });
}

export function useUpdateLandingPage(id: number) {
  const { api, keyBase } = useLandingScope();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<LandingPageFormValues & SeoFormValues & ChromeFormValues>) => api.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: keyBase }),
  });
}

export function useSubmitLandingPage() {
  const { api, keyBase } = useLandingScope();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.submit(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: keyBase }),
  });
}

export function useDeleteLandingPage() {
  const { api, keyBase } = useLandingScope();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: keyBase }),
  });
}

// ── Blocks ──

export function useLandingPageBlocks(pageId: number | null) {
  const { api, keyBase } = useLandingScope();
  return useQuery({
    queryKey: [...keyBase, 'blocks', pageId ?? 0],
    queryFn: () => api.blocks(pageId as number),
    enabled: pageId !== null,
  });
}

export function useAddBlock(pageId: number) {
  const { api, keyBase } = useLandingScope();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (type: BlockType) => api.addBlock(pageId, type),
    onSuccess: () => qc.invalidateQueries({ queryKey: [...keyBase, 'blocks', pageId] }),
  });
}

export function useUpdateBlock(pageId: number) {
  const { api, keyBase } = useLandingScope();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (args: { blockId: number; values: Partial<Pick<LandingPageBlock, 'is_visible' | 'sort_order' | 'config'>> }) =>
      api.updateBlock(pageId, args.blockId, args.values),
    onSuccess: () => qc.invalidateQueries({ queryKey: [...keyBase, 'blocks', pageId] }),
  });
}

export function useDeleteBlock(pageId: number) {
  const { api, keyBase } = useLandingScope();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (blockId: number) => api.deleteBlock(pageId, blockId),
    onSuccess: () => qc.invalidateQueries({ queryKey: [...keyBase, 'blocks', pageId] }),
  });
}

export function useLandingStats(pageId: number | null, range?: { from?: string; to?: string }) {
  const { api, keyBase } = useLandingScope();
  return useQuery({
    queryKey: [...keyBase, 'stats', pageId, range],
    queryFn: () => api.stats(pageId as number, range),
    enabled: pageId !== null,
  });
}

export function useReorderBlocks(pageId: number) {
  const { api, keyBase } = useLandingScope();
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: ReorderPayload) => api.reorderBlocks(pageId, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: [...keyBase, 'blocks', pageId] }),
  });
}

export function useGenerateLanding() {
  const { api } = useLandingScope();
  return useMutation({
    mutationFn: (input: { clinic_id?: number | null; service?: string; city_id?: number | null; category_id?: number | null }) =>
      api.generate(input),
  });
}

export function useLandingCustomers(pageId: number | null, params: { page?: number; per_page?: number; search?: string; status?: string } = {}) {
  const { api, keyBase } = useLandingScope();
  return useQuery({
    queryKey: [...keyBase, 'customers', pageId, params],
    queryFn: () => api.customers(pageId as number, params),
    enabled: pageId !== null,
  });
}
