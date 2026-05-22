import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { citiesApi, type CityListParams } from './api/cities.api';
import type { CityFormValues } from './types';

const KEY = ['admin', 'cities'] as const;

export function useCities(params: CityListParams = {}) {
  return useQuery({
    queryKey: [...KEY, 'list', params],
    queryFn: () => citiesApi.list(params),
  });
}

export function useCity(id: number | null) {
  return useQuery({
    queryKey: [...KEY, 'detail', id],
    queryFn: () => citiesApi.get(id as number),
    enabled: id !== null,
  });
}

export function useCreateCity() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: CityFormValues) => citiesApi.create(values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateCity(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<CityFormValues>) => citiesApi.update(id, values),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteCity() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => citiesApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}
