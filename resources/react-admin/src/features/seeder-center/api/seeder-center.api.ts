import { apiClient } from '@/lib/api-client';
import type { SeederBatch, SeederBatchRun, SeederConflict } from '../types';

export const seederCenterApi = {
  inventory: async () => {
    const res = await apiClient.get<{ data: SeederBatch[] }>('/admin/seeder-center');
    return res.data.data;
  },
  conflicts: async (batch: string) => {
    const res = await apiClient.get<{ data: SeederConflict[] }>(`/admin/seeder-center/${batch}/conflicts`);
    return res.data.data;
  },
  hide: async (batch: string) => {
    const res = await apiClient.post<{ data: { hidden: number } }>(`/admin/seeder-center/${batch}/hide`);
    return res.data.data;
  },
  unhide: async (batch: string) => {
    const res = await apiClient.post<{ data: { restored: number } }>(`/admin/seeder-center/${batch}/unhide`);
    return res.data.data;
  },
  purge: async (batch: string, force = false) => {
    const res = await apiClient.post<{ data: { deleted: number } }>(`/admin/seeder-center/${batch}/purge`, { force });
    return res.data.data;
  },
  reseed: async (batch: string) => {
    const res = await apiClient.post<{ data: SeederBatchRun }>(`/admin/seeder-center/${batch}/reseed`);
    return res.data.data;
  },
  runStatus: async (id: number) => {
    const res = await apiClient.get<{ data: SeederBatchRun }>(`/admin/seeder-center/runs/${id}`);
    return res.data.data;
  },
};
