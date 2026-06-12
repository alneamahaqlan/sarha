import { useQuery, keepPreviousData } from '@tanstack/react-query';
import { adminFavoritesApi } from './api';

const KEY = ['admin', 'favorites'] as const;

export function useFavorites(page: number) {
  return useQuery({
    queryKey: [...KEY, page],
    queryFn: () => adminFavoritesApi.index(page),
    placeholderData: keepPreviousData,
    staleTime: 30_000,
  });
}
