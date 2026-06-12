import { apiClient } from '@/lib/api-client';
import type { CreateReminderInput, CustomerReminder, ReminderListStatus } from './types';

export const remindersApi = {
  list: async (status: ReminderListStatus = 'open', mine = false) => {
    const res = await apiClient.get<{ data: CustomerReminder[] }>('/clinic/reminders', {
      params: mine ? { status, mine: 1 } : { status },
    });
    return res.data.data;
  },

  create: async (payload: CreateReminderInput) => {
    const res = await apiClient.post<{ data: CustomerReminder }>('/clinic/reminders', payload);
    return res.data.data;
  },

  complete: async (id: number) => {
    const res = await apiClient.post<{ data: CustomerReminder }>(`/clinic/reminders/${id}/complete`);
    return res.data.data;
  },

  cancel: async (id: number) => {
    await apiClient.post(`/clinic/reminders/${id}/cancel`);
  },
};
