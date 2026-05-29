import { apiClient } from '@/lib/api-client';
import type { SingleResponse } from '@/types/api';
import type { SystemSetting, SystemSettingFormValues } from '../types';

// Settings index is NOT paginated (Filament uses paginated(false)).
interface SettingsListResponse {
  data: SystemSetting[];
}

export const systemSettingsApi = {
  list: async (search?: string, group?: string) => {
    const params: Record<string, string> = {};
    if (search) params.search = search;
    if (group) params['filter[group]'] = group;
    const res = await apiClient.get<SettingsListResponse>('/admin/system-settings', { params });
    return res.data.data;
  },
  update: async (id: number, values: Partial<SystemSettingFormValues>) => {
    const res = await apiClient.patch<SingleResponse<SystemSetting>>(`/admin/system-settings/${id}`, values);
    return res.data.data;
  },
};
