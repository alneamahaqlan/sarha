import { apiClient } from '@/lib/api-client';
import type { SingleResponse } from '@/types/api';
import type {
  AiRestriction,
  AiRestrictionFormValues,
  AiRestrictionType,
  AiResponseTemplate,
  AiResponseTemplateFormValues,
} from './types';

export const aiRestrictionsApi = {
  list: async (type?: AiRestrictionType) => {
    const res = await apiClient.get<{ data: AiRestriction[] }>('/admin/ai-restrictions', {
      params: type ? { type } : undefined,
    });
    return res.data.data;
  },
  create: async (values: AiRestrictionFormValues) => {
    const res = await apiClient.post<SingleResponse<AiRestriction>>('/admin/ai-restrictions', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<AiRestrictionFormValues>) => {
    const res = await apiClient.patch<SingleResponse<AiRestriction>>(`/admin/ai-restrictions/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/admin/ai-restrictions/${id}`);
  },
};

export const aiResponseTemplatesApi = {
  list: async () => {
    const res = await apiClient.get<{ data: AiResponseTemplate[] }>('/admin/ai-response-templates');
    return res.data.data;
  },
  create: async (values: AiResponseTemplateFormValues) => {
    const res = await apiClient.post<SingleResponse<AiResponseTemplate>>('/admin/ai-response-templates', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<AiResponseTemplateFormValues>) => {
    const res = await apiClient.patch<SingleResponse<AiResponseTemplate>>(`/admin/ai-response-templates/${id}`, values);
    return res.data.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/admin/ai-response-templates/${id}`);
  },
};
