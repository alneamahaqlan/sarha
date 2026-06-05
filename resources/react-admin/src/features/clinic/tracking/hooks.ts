import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicTrackingApi, type Pixel } from './api';

const KEY = ['clinic', 'tracking'] as const;

export function useClinicTracking() {
  return useQuery({ queryKey: KEY, queryFn: clinicTrackingApi.show });
}

export function useUpdateClinicTracking() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: { tracking_pixels: Pixel[]; advanced_matching_optin: boolean }) => clinicTrackingApi.update(v),
    onSuccess: (data) => qc.setQueryData(KEY, data),
  });
}

export function useRequestClinicTracking() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => clinicTrackingApi.request(),
    onSuccess: (data) => qc.setQueryData(KEY, data),
  });
}
