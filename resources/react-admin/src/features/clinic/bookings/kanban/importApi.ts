import { useMutation, useQuery } from '@tanstack/react-query';

import { apiClient } from '@/lib/api-client';
import type { AcquisitionSource } from './types';

/** Column letters keyed by our field. Empty/undefined → field not mapped. */
export interface ImportColumnMap {
  customer_name: string;
  customer_phone: string;
  service?: string;
  appointment_at?: string;
  notes?: string;
}

export interface ImportDefaults {
  status?: 'new' | 'contacted' | 'appointment_set';
  acquisition_source?: AcquisitionSource | null;
  assignee_type?: 'Clinic' | 'ClinicTeamMember' | null;
  assignee_id?: number | null;
}

export interface ServiceSuggestion {
  service_id: number;
  name: string;
  sub_clinic_name: string | null;
  score: number;
}

export interface ServiceAnalysis {
  name: string;
  status: 'matched' | 'unmatched';
  service: { id: number; name: string; sub_clinic_id: number | null; sub_clinic_name: string | null } | null;
  suggestions: ServiceSuggestion[];
}

export interface ImportPreviewResult {
  rows: Array<Record<string, string | number>>;
  services: ServiceAnalysis[];
  total: number;
  overlap: Array<{ row_from: number; row_to: number; imported_at: string | null }>;
  /** Present only on the file-upload preview — handle to re-read on commit. */
  file_token?: string;
}

/** One resolution the clinic makes for an unmatched service name. */
export type ServiceResolution =
  | { name: string; action: 'map'; service_id: number }
  | { name: string; action: 'create'; sub_clinic_id: number; category_ids?: number[] };

export interface ImportCommitInput {
  sheet_url: string;
  column_map: ImportColumnMap;
  row_from: number;
  row_to: number;
  defaults: ImportDefaults;
  service_resolutions?: ServiceResolution[];
  save_source?: boolean;
  source_name?: string;
  import_source_id?: number | null;
}

export interface ImportCommitResult {
  rows_imported: number;
  rows_skipped: number;
  run_id: number;
  source_id: number | null;
}

/** Commit input for the file-upload variant (references the stored upload). */
export interface ImportFileCommitInput {
  file_token: string;
  column_map: ImportColumnMap;
  row_from: number;
  row_to: number;
  defaults: ImportDefaults;
  service_resolutions?: ServiceResolution[];
}

export interface SavedImportSource {
  id: number;
  name: string;
  sheet_url: string;
  column_map: ImportColumnMap;
  defaults: ImportDefaults | null;
  service_aliases: Record<string, unknown> | null;
  last_imported_at: string | null;
  created_at: string | null;
  runs_count?: number;
}

export const bookingImportApi = {
  preview: async (input: {
    sheet_url: string;
    column_map: ImportColumnMap;
    row_from: number;
    row_to: number;
    import_source_id?: number | null;
  }): Promise<ImportPreviewResult> => {
    const res = await apiClient.post<{ data: ImportPreviewResult }>('/clinic/bookings/imports/preview', input);
    return res.data.data;
  },

  commit: async (input: ImportCommitInput): Promise<ImportCommitResult> => {
    const res = await apiClient.post<{ data: ImportCommitResult }>('/clinic/bookings/imports/commit', input);
    return res.data.data;
  },

  previewFile: async (input: {
    file: File;
    column_map: ImportColumnMap;
    row_from: number;
    row_to: number;
  }): Promise<ImportPreviewResult> => {
    const fd = new FormData();
    fd.append('file', input.file);
    fd.append('row_from', String(input.row_from));
    fd.append('row_to', String(input.row_to));
    // Bracketed keys so Laravel parses column_map as an array.
    (Object.entries(input.column_map) as Array<[string, string | undefined]>).forEach(([k, v]) => {
      if (v) fd.append(`column_map[${k}]`, v);
    });
    const res = await apiClient.post<{ data: ImportPreviewResult }>('/clinic/bookings/imports/file/preview', fd);
    return res.data.data;
  },

  commitFile: async (input: ImportFileCommitInput): Promise<ImportCommitResult> => {
    const res = await apiClient.post<{ data: ImportCommitResult }>('/clinic/bookings/imports/file/commit', input);
    return res.data.data;
  },

  sources: async (): Promise<SavedImportSource[]> => {
    const res = await apiClient.get<{ data: SavedImportSource[] }>('/clinic/bookings/imports/sources');
    return res.data.data;
  },

  deleteSource: async (id: number): Promise<void> => {
    await apiClient.delete(`/clinic/bookings/imports/sources/${id}`);
  },
};

export function useImportSources() {
  return useQuery({
    queryKey: ['clinic', 'bookings', 'import-sources'],
    queryFn: bookingImportApi.sources,
    staleTime: 30_000,
  });
}

export function useImportPreview() {
  return useMutation({ mutationFn: bookingImportApi.preview });
}

export function useImportCommit() {
  return useMutation({ mutationFn: bookingImportApi.commit });
}

export function useImportFilePreview() {
  return useMutation({ mutationFn: bookingImportApi.previewFile });
}

export function useImportFileCommit() {
  return useMutation({ mutationFn: bookingImportApi.commitFile });
}
