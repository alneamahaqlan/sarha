export interface WhatsAppSender {
  id: number;
  label: string | null;
  phone: string;
  provider: string;
  profile_id: string | null;
  /** True when a token is stored. The token itself is never returned. */
  token_set: boolean;
  is_active: boolean;
  priority: number;
  failure_count: number;
  last_used_at: string | null;
  last_failed_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface WhatsAppSenderFormValues {
  label?: string | null;
  phone: string;
  profile_id?: string | null;
  token?: string | null;
  is_active: boolean;
  priority: number;
}
