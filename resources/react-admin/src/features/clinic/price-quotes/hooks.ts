import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicQuotesApi, type ListParams, type QuoteReplyValues } from './api';

const KEY = ['clinic', 'price-quotes'] as const;

export function useClinicQuotes(params: ListParams = {}) {
  return useQuery({ queryKey: [...KEY, 'list', params], queryFn: () => clinicQuotesApi.list(params) });
}

export function useReplyClinicQuote(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: QuoteReplyValues) => clinicQuotesApi.reply(id, v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useQuoteAccess() {
  return useQuery({ queryKey: [...KEY, 'access'], queryFn: () => clinicQuotesApi.access() });
}

export function useRequestQuoteAccess() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => clinicQuotesApi.requestAccess(),
    onSuccess: () => qc.invalidateQueries({ queryKey: [...KEY, 'access'] }),
  });
}
