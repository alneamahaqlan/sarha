import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { clinicTeamApi } from './api';
import type { ActivityFilters, TeamMemberEditValues, TeamMemberFormValues } from './types';

const KEY = ['clinic', 'team'] as const;
const ACTIVITY_KEY = ['clinic', 'team-activity'] as const;

export function useClinicTeam(q?: string) {
  return useQuery({
    queryKey: [...KEY, 'list', q ?? null],
    queryFn: () => clinicTeamApi.list(q),
  });
}

export function useCreateTeamMember() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: TeamMemberFormValues) => clinicTeamApi.create(v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useUpdateTeamMember(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: TeamMemberEditValues) => clinicTeamApi.update(id, v),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useDeleteTeamMember() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => clinicTeamApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useRegenerateTeamMemberPassword() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => clinicTeamApi.regeneratePassword(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEY }),
  });
}

export function useClinicTeamActivity(filters: ActivityFilters = {}) {
  return useQuery({
    queryKey: [...ACTIVITY_KEY, filters],
    queryFn: () => clinicTeamApi.activity(filters),
  });
}
