import { apiClient } from '@/lib/api-client';
import type { PaginatedResponse, SingleResponse } from '@/types/api';
import type {
  ActivityFilters,
  ClinicActivityLog,
  ClinicTeamMember,
  TeamMemberEditValues,
  TeamMemberFormValues,
  TeamMemberWithTempPassword,
} from './types';

/**
 * Clinic-owner-only API for managing team members + reading the
 * activity feed. All endpoints sit under /clinic/* (the existing
 * clinic guard) with route-level `clinic.role:team.manage` /
 * `clinic.role:team_activity.view` gates.
 */
export const clinicTeamApi = {
  list: async (q?: string) => {
    const res = await apiClient.get<{ data: ClinicTeamMember[] }>('/clinic/team', {
      params: q ? { q } : {},
    });
    return res.data.data;
  },
  create: async (v: TeamMemberFormValues): Promise<TeamMemberWithTempPassword> => {
    const res = await apiClient.post<TeamMemberWithTempPassword>('/clinic/team', v);
    return res.data;
  },
  update: async (id: number, v: TeamMemberEditValues) => {
    const res = await apiClient.patch<SingleResponse<ClinicTeamMember>>(`/clinic/team/${id}`, v);
    return res.data.data;
  },
  regeneratePassword: async (id: number): Promise<TeamMemberWithTempPassword> => {
    const res = await apiClient.post<TeamMemberWithTempPassword>(`/clinic/team/${id}/regenerate-password`);
    return res.data;
  },
  delete: async (id: number) => {
    await apiClient.delete(`/clinic/team/${id}`);
  },
  activity: async (filters: ActivityFilters = {}) => {
    const res = await apiClient.get<PaginatedResponse<ClinicActivityLog>>('/clinic/team-activity', {
      params: filters,
    });
    return res.data;
  },
};
