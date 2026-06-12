import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { remindersApi } from './api';
import type { CreateReminderInput, ReminderListStatus } from './types';

const KEY = ['clinic', 'reminders'] as const;

export function useReminders(status: ReminderListStatus = 'open', mine = false) {
  return useQuery({
    queryKey: [...KEY, 'list', status, mine],
    queryFn: () => remindersApi.list(status, mine),
    staleTime: 15_000,
  });
}

export function useCreateReminder() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateReminderInput) => remindersApi.create(payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEY });
    },
  });
}

export function useCompleteReminder() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => remindersApi.complete(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEY });
    },
  });
}

export function useCancelReminder() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => remindersApi.cancel(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEY });
    },
  });
}
