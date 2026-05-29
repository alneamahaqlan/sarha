import { apiClient } from '@/lib/api-client';
import type { SingleResponse } from '@/types/api';
import type { ClinicPageSection, ClinicPageSectionFormValues } from './types';

export interface ReorderPayload {
  order: { id: number; sort_order: number }[];
}

export const clinicPageBuilderApi = {
  list: async () => {
    const res = await apiClient.get<{ data: ClinicPageSection[] }>('/clinic/page-sections');
    return res.data.data;
  },
  update: async (id: number, values: ClinicPageSectionFormValues) => {
    const res = await apiClient.patch<SingleResponse<ClinicPageSection>>(
      `/clinic/page-sections/${id}`,
      values,
    );
    return res.data.data;
  },
  reorder: async (payload: ReorderPayload) => {
    await apiClient.post('/clinic/page-sections/reorder', payload);
  },
};
