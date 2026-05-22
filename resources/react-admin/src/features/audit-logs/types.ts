export interface AuditLog {
  id: number;
  admin_id: number | null;
  admin_name: string | null;
  action: string;
  model_type: string | null;
  model_basename: string | null;
  model_id: number | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string | null;
}
