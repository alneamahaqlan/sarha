import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicProfileApi, type ProfileFormValues, type WorkingHourRow } from './api';

const KEY = ['clinic', 'profile'] as const;
const WORKING_HOURS_KEY = ['clinic', 'working-hours'] as const;
const SUBSCRIPTION_KEY = ['clinic', 'subscription'] as const;

export function useClinicProfile() {
  return useQuery({ queryKey: KEY, queryFn: () => clinicProfileApi.show() });
}

export function useUpdateClinicProfile() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: ProfileFormValues) => clinicProfileApi.update(v),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: KEY });
      qc.invalidateQueries({ queryKey: ['auth', 'me'] });
    },
  });
}

export function useExtractCoords() {
  return useMutation({ mutationFn: (url: string) => clinicProfileApi.extractCoords(url) });
}

export function useSyncReviews() {
  return useMutation({ mutationFn: () => clinicProfileApi.syncReviews() });
}

export function useWorkingHours() {
  return useQuery({ queryKey: WORKING_HOURS_KEY, queryFn: () => clinicProfileApi.workingHours() });
}

export function useUpdateWorkingHours() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (hours: WorkingHourRow[]) => clinicProfileApi.updateWorkingHours(hours),
    onSuccess: () => qc.invalidateQueries({ queryKey: WORKING_HOURS_KEY }),
  });
}

export function useClinicSubscription() {
  return useQuery({ queryKey: SUBSCRIPTION_KEY, queryFn: () => clinicProfileApi.subscription() });
}
