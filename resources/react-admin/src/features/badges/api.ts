import { apiClient } from '@/lib/api-client';
import type { Badge, BadgeFormValues, BadgeMeta, BadgeTargetType, TargetLite } from './types';

const BASE = '/admin/badges';

export const badgesApi = {
  list: async (): Promise<Badge[]> => (await apiClient.get<{ data: Badge[] }>(BASE)).data.data,
  show: async (id: number): Promise<Badge> => (await apiClient.get<{ data: Badge }>(`${BASE}/${id}`)).data.data,
  meta: async (): Promise<BadgeMeta> => (await apiClient.get<{ data: BadgeMeta }>(`${BASE}/rules`)).data.data,
  create: async (v: BadgeFormValues): Promise<Badge> => (await apiClient.post<{ data: Badge }>(BASE, v)).data.data,
  update: async (id: number, v: BadgeFormValues): Promise<Badge> =>
    (await apiClient.put<{ data: Badge }>(`${BASE}/${id}`, v)).data.data,
  remove: async (id: number): Promise<void> => {
    await apiClient.delete(`${BASE}/${id}`);
  },
  recompute: async (): Promise<Record<string, number>> =>
    (await apiClient.post<{ data: { summary: Record<string, number> } }>(`${BASE}/recompute`)).data.data.summary,
  syncTargets: async (id: number, type: BadgeTargetType, ids: number[]): Promise<void> => {
    await apiClient.post(`${BASE}/${id}/targets`, { type, ids });
  },
  searchTargets: async (type: BadgeTargetType, search: string): Promise<TargetLite[]> =>
    (await apiClient.get<{ data: TargetLite[] }>(`${BASE}/targets/search`, { params: { type, search } })).data.data,
};
