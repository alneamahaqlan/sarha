import { apiClient } from '@/lib/api-client';
import type { SingleResponse } from '@/types/api';
import type { WhatsAppSender, WhatsAppSenderFormValues } from '../types';

// Index is NOT paginated — the catalogue is capped at 5 rows.
interface SendersListResponse {
  data: WhatsAppSender[];
}

export const whatsappSendersApi = {
  list: async () => {
    const res = await apiClient.get<SendersListResponse>('/admin/whatsapp-senders');
    return res.data.data;
  },
  create: async (values: WhatsAppSenderFormValues) => {
    const res = await apiClient.post<SingleResponse<WhatsAppSender>>('/admin/whatsapp-senders', values);
    return res.data.data;
  },
  update: async (id: number, values: Partial<WhatsAppSenderFormValues>) => {
    const res = await apiClient.patch<SingleResponse<WhatsAppSender>>(`/admin/whatsapp-senders/${id}`, values);
    return res.data.data;
  },
  remove: async (id: number) => {
    await apiClient.delete(`/admin/whatsapp-senders/${id}`);
  },
  test: async (id: number, phone: string) => {
    const res = await apiClient.post<{ sent: boolean; message: string }>(
      `/admin/whatsapp-senders/${id}/test`,
      { phone },
    );
    return res.data;
  },
};
