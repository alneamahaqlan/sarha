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

/** Sub-clinics of a complex — only fetches once a clinic is chosen. */
export function useSubClinicLookup(clinicId?: number) {
  return useQuery({
    enabled: !!clinicId,
    queryKey: ['lookups', 'sub-clinics', clinicId ?? 0],
    queryFn: () => lookupsApi.subClinics(clinicId!),
    staleTime: 5 * 60_000,
  });
}

/** Services of a complex — only fetches once a clinic is chosen. */
export function useServiceLookup(clinicId?: number) {
  return useQuery({
    enabled: !!clinicId,
    queryKey: ['lookups', 'services', clinicId ?? 0],
    queryFn: () => lookupsApi.services(clinicId!),
    staleTime: 5 * 60_000,
  });
}

/** Offers of a complex — for landing-page manual block selection. */
export function useOfferLookup(clinicId?: number) {
  return useQuery({
    enabled: !!clinicId,
    queryKey: ['lookups', 'offers', clinicId ?? 0],
    queryFn: () => lookupsApi.offers(clinicId!),
    staleTime: 5 * 60_000,
  });
}

/** Doctors of a complex — for landing-page manual block selection. */
export function useDoctorLookup(clinicId?: number) {
  return useQuery({
    enabled: !!clinicId,
    queryKey: ['lookups', 'doctors', clinicId ?? 0],
    queryFn: () => lookupsApi.doctors(clinicId!),
    staleTime: 5 * 60_000,
  });
}

/** Before/after cases of a complex — for landing-page manual gallery. */
export function useBeforeAfterLookup(clinicId?: number) {
  return useQuery({
    enabled: !!clinicId,
    queryKey: ['lookups', 'before-after', clinicId ?? 0],
    queryFn: () => lookupsApi.beforeAfter(clinicId!),
    staleTime: 5 * 60_000,
  });
}
