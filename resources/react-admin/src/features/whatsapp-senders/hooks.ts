import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { whatsappSendersApi } from './api/whatsapp-senders.api';
import type { WhatsAppSenderFormValues } from './types';

const KEY = ['admin', 'whatsapp-senders'] as const;

export function useWhatsAppSenders() {
  return useQuery({
    queryKey: [...KEY, 'list'],
    queryFn: () => whatsappSendersApi.list(),
  });
}

export function useCreateWhatsAppSender() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: WhatsAppSenderFormValues) => whatsappSendersApi.create(values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateWhatsAppSender(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<WhatsAppSenderFormValues>) => whatsappSendersApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteWhatsAppSender() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => whatsappSendersApi.remove(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useTestWhatsAppSender() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, phone }: { id: number; phone: string }) => whatsappSendersApi.test(id, phone),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
