import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicQuotesApi, type ClinicQuoteUpdateValues, type ListParams } from './api';

const KEY = ['clinic', 'price-quotes'] as const;

export function useClinicQuotes(params: ListParams = {}) {
  return useQuery({ queryKey: [...KEY, 'list', params], queryFn: () => clinicQuotesApi.list(params) });
}

export function useUpdateClinicQuote(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: ClinicQuoteUpdateValues) => clinicQuotesApi.update(id, v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
