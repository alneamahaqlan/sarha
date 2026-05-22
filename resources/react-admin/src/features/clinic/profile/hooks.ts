import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicProfileApi, type ProfileFormValues } from './api';

const KEY = ['clinic', 'profile'] as const;

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
