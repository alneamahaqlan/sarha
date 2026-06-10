import { apiClient } from '@/lib/api-client';

export type GateStatus = 'active' | 'disabled' | 'pending' | 'rejected';
export type GateAction = 'approve' | 'reject' | 'disable';

export interface GateMeta {
  key: string;
  label_ar: string;
  label_en: string;
  default: GateStatus;
}

export interface PendingItem {
  gate: string;
  gate_label_ar: string;
  gate_label_en: string;
  clinic_id: number;
  clinic_name: string;
  requested_at: string | null;
}

export interface QueueItem {
  key: string;
  count: number;
  route: string;
}

export interface ClinicGates {
  id: number;
  name: string;
  gates: Record<string, GateStatus>;
}

export type FlagValue = string | number | boolean | null;

export interface PackageRow {
  id: number;
  name_ar: string;
  name_en: string;
  flags: Record<string, FlagValue>;
}

export const accessCenterApi = {
  meta: async () =>
    (await apiClient.get<{ data: { gates: GateMeta[] } }>('/admin/access-center/meta')).data.data,

  pending: async () =>
    (await apiClient.get<{ data: { pending: PendingItem[]; queues: QueueItem[] } }>('/admin/access-center/pending')).data.data,

  clinics: async (search: string) =>
    (await apiClient.get<{ data: ClinicGates[] }>('/admin/access-center/clinics', {
      params: search ? { search } : {},
    })).data.data,

  packages: async () =>
    (await apiClient.get<{ data: { flags: string[]; packages: PackageRow[] } }>('/admin/access-center/packages')).data.data,

  action: async (gate: string, clinicId: number, action: GateAction, reason?: string) =>
    (await apiClient.post(`/admin/access-center/gates/${gate}/clinics/${clinicId}/action`, { action, ...(reason ? { reason } : {}) })).data,
};
