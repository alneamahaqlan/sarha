import { useQuery } from '@tanstack/react-query';
import { lookupsApi } from './api';

export function useClinicLookup(search?: string) {
  return useQuery({
    queryKey: ['lookups', 'clinics', search ?? ''],
    queryFn: () => lookupsApi.clinics(search),
    staleTime: 60_000,
  });
}

export function useCityLookup(search?: string) {
  return useQuery({
    queryKey: ['lookups', 'cities', search ?? ''],
    queryFn: () => lookupsApi.cities(search),
    staleTime: 5 * 60_000,
  });
}

export function useCategoryLookup(search?: string) {
  return useQuery({
    queryKey: ['lookups', 'categories', search ?? ''],
    queryFn: () => lookupsApi.categories(search),
    staleTime: 5 * 60_000,
  });
}

export function useAdminLookup(role?: string, search?: string) {
  return useQuery({
    queryKey: ['lookups', 'admins', role ?? '', search ?? ''],
    queryFn: () => lookupsApi.admins(role, search),
    staleTime: 5 * 60_000,
  });
}
