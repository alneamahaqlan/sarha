export type AiRestrictionType =
  | 'banned_topic'
  | 'emergency_keyword'
  | 'clinic_blocklist'
  | 'category_blocklist';

export interface AiRestriction {
  id: number;
  type: AiRestrictionType;
  value: string;
  /** For blocklist rows the server resolves a human-readable label so the UI doesn't fan out lookups. */
  value_label: string | null;
  response_override: string | null;
  is_active: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface AiRestrictionFormValues {
  type: AiRestrictionType;
  value: string;
  response_override?: string | null;
  is_active?: boolean;
}

export interface AiResponseTemplate {
  id: number;
  label: string;
  content: string;
  is_active: boolean;
  sort_order: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface AiResponseTemplateFormValues {
  label: string;
  content: string;
  is_active?: boolean;
  sort_order?: number;
}
