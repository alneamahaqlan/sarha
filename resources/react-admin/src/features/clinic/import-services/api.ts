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

/** A service the AI pulled out of free-form text, shown for review before import. */
export interface DraftService {
  name: string;
  description: string | null;
  price: number | null;
  category_ids: number[];
}

/** An offer the AI pulled out of free-form text. `service_id` is the AI's
 *  suggested link to an existing service (null = a general, clinic-wide promo). */
export interface DraftOffer {
  title: string;
  description: string | null;
  old_price: number | null;
  price: number | null;
  service_id: number | null;
  type: 'service' | 'general';
}

export interface DraftCatalog {
  services: DraftService[];
  offers: DraftOffer[];
}

/** What the clinic ticked + edited in the review table, sent back to save. */
export interface ConfirmPayload {
  services: DraftService[];
  offers: Array<DraftOffer & { ends_at?: string | null }>;
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
  extractText: async (text: string) => {
    const res = await apiClient.post<{ data: DraftCatalog }>(
      '/clinic/import-services/extract-text',
      { text },
    );
    return res.data.data;
  },
  confirm: async (payload: ConfirmPayload) => {
    const res = await apiClient.post<{
      data: { services_created: number; offers_created: number };
      message: string;
    }>('/clinic/import-services/confirm', payload);
    return res.data;
  },
  execute: async (payload: ExecutePayload) => {
    const res = await apiClient.post<{ data: { imported: number }; message: string }>(
      '/clinic/import-services/execute',
      payload,
    );
    return res.data;
  },
};
