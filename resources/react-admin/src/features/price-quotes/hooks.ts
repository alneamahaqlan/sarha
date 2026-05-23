import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { priceQuotesApi, type PriceQuoteListParams } from './api/price-quotes.api';
import type { PriceQuoteFormValues } from './types';

const KEY = ['admin', 'price-quotes'] as const;

export function usePriceQuotes(params: PriceQuoteListParams = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', params],
    queryFn: () => priceQuotesApi.list(params),
  });
}

export function useUpdatePriceQuote(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<PriceQuoteFormValues>) => priceQuotesApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeletePriceQuote() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => priceQuotesApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
