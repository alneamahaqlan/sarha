import { apiClient } from '@/lib/api-client';

export interface ColumnAnalysis {
  column: string;
  mapped_to: string | null;
  confidence: number;
  reason: string;
}

export interface AnalyzeResponse {
  data: {
    headers: string[];
    rows: string[][];
    analysis: ColumnAnalysis[];
  };
}

export interface ExecutePayload {
  headers: string[];
  rows: string[][];
  mapping: Record<number, string | null>;
}

export const importServicesApi = {
  analyze: async (file: File) => {
    const fd = new FormData();
    fd.append('file', file);
    const res = await apiClient.post<AnalyzeResponse>('/clinic/import-services/analyze', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return res.data.data;
  },
  execute: async (payload: ExecutePayload) => {
    const res = await apiClient.post<{ data: { imported: number }; message: string }>(
      '/clinic/import-services/execute',
      payload,
    );
    return res.data;
  },
};
